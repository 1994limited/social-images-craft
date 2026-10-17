<?php

namespace nineteenninetyfour\socialimages\tests\unit;

use Craft;
use craft\elements\Entry;
use craft\events\ElementEvent;
use craft\fs\Local;
use craft\services\Elements;
use NineteenNinetyFour\SocialImages\Core\Rendering\Exceptions\RenderFailed;
use nineteenninetyfour\socialimages\images\Card;
use nineteenninetyfour\socialimages\jobs\GenerateImage;

class PipelineTest extends TestCase
{
    public function test_saving_an_entry_queues_its_image_and_the_queue_makes_it(): void
    {
        $this->template();
        $this->useTemplate('basic', ['journal']);

        $entry = $this->entry();
        $card = $this->plugin()->cards->for($entry);

        $this->assertFalse($card->exists());
        $this->runQueue();

        $this->assertTrue($card->exists());
        $this->assertMatchesRegularExpression('#^social-images/'.$entry->id.'/'.$entry->siteId.'/og-[a-f0-9]{12}\.png$#', $card->path());
        $this->assertCount(1, $this->camera->captures);
        $this->assertStringContainsString('Hello world', $this->pageData($this->camera->captures[0]['html']));
        $this->assertSame([1200, 630], [$this->camera->captures[0]['width'], $this->camera->captures[0]['height']]);
    }

    public function test_an_entry_with_no_rule_gets_no_image(): void
    {
        $this->template();
        $this->useTemplate('basic', ['books']);

        $entry = $this->entry('journal');

        $this->assertNull($this->plugin()->cards->for($entry));
        $this->runQueue();
        $this->assertCount(0, $this->camera->captures);
    }

    public function test_the_fingerprint_changes_with_what_the_image_shows_only(): void
    {
        $this->template();
        $this->useTemplate('basic', ['journal']);
        $this->settings(['generateOnSave' => false]);

        $entry = $this->entry('journal', 'Hello', ['excerpt' => 'One']);
        $hash = $this->plugin()->cards->for($entry)->hash();

        $entry->setFieldValue('excerpt', 'Two');
        Craft::$app->getElements()->saveElement($entry);
        $this->assertSame($hash, $this->plugin()->cards->for($this->fresh($entry))->hash());

        $entry->title = 'Goodbye';
        Craft::$app->getElements()->saveElement($entry);
        $this->assertNotSame($hash, $changed = $this->plugin()->cards->for($this->fresh($entry))->hash());

        // Editing the template changes every image made from it.
        $this->template(layers: [['id' => 'title', 'type' => 'text', 'text' => '{title}', 'size' => 60]]);
        $this->assertNotSame($changed, $this->plugin()->cards->for($this->fresh($entry))->hash());
    }

    public function test_a_new_image_replaces_the_old_only_once_its_made(): void
    {
        $this->template();
        $this->useTemplate('basic', ['journal']);

        $entry = $this->entry();
        $this->runQueue();
        $old = $this->plugin()->cards->for($this->fresh($entry));
        $old->hash();

        $entry->title = 'Changed';
        Craft::$app->getElements()->saveElement($entry);
        $new = $this->plugin()->cards->for($this->fresh($entry));

        // Until the new one's made, pages get the old one.
        $this->assertSame($old->url(), $this->plugin()->cards->image($this->fresh($entry))['url']);

        $this->runQueue();
        $this->assertTrue($new->exists());
        $this->assertFalse($old->exists());
        $this->assertSame($new->url(), $this->plugin()->cards->image($this->fresh($entry))['url']);
    }

    public function test_a_queued_render_stands_down_when_the_entry_has_moved_on(): void
    {
        $this->template();
        $this->useTemplate('basic', ['journal']);
        $this->settings(['generateOnSave' => false]);

        $entry = $this->entry();
        $stale = $this->plugin()->cards->for($entry)->hash();

        $entry->title = 'Changed';
        Craft::$app->getElements()->saveElement($entry);

        $job = new GenerateImage(['entryId' => $entry->id, 'siteId' => $entry->siteId, 'hash' => $stale]);

        $this->assertNull($job->render());
        $this->assertCount(0, $this->camera->captures);
    }

    public function test_a_page_view_queues_one_render(): void
    {
        $this->template();
        $this->useTemplate('basic', ['journal']);
        $this->settings(['generateOnSave' => false]);

        $entry = $this->entry();
        $queue = Craft::$app->getQueue();
        $before = $queue->getTotalJobs();

        $this->plugin()->cards->image($entry);
        $this->plugin()->cards->image($entry);

        $this->assertSame($before + 1, $queue->getTotalJobs());
    }

    public function test_deleting_an_entry_removes_its_images(): void
    {
        $this->template();
        $this->useTemplate('basic', ['journal']);

        $entry = $this->entry();
        $this->runQueue();
        $card = $this->plugin()->cards->for($entry);
        $this->assertTrue($card->exists());

        Craft::$app->getElements()->deleteElement($entry);

        $this->assertFalse($card->exists());
    }

    protected function fresh(Entry $entry): Entry
    {
        return Entry::find()->id($entry->id)->siteId($entry->siteId)->status(null)->one();
    }

    public function test_something_that_isnt_a_png_is_not_saved(): void
    {
        $this->template();
        $this->useTemplate('basic', ['journal']);
        $this->settings(['generateOnSave' => false]);
        $entry = $this->entry();
        $this->plugin()->set('camera', new class extends FakeCamera
        {
            public function capture(string $html, int $width, int $height): string
            {
                return 'not an image';
            }
        });

        try {
            $this->plugin()->cards->for($entry)->render();
            $this->fail('Saved something that isn\'t a PNG.');
        } catch (RenderFailed) {
            $this->assertFalse($this->plugin()->cards->for($entry)->exists());
        }
    }

    public function test_images_are_written_whole_and_remembered_as_there(): void
    {
        $this->template();
        $this->useTemplate('basic', ['journal']);
        $entry = $this->entry();
        $this->runQueue();

        $card = $this->plugin()->cards->for($entry);
        $folder = CRAFT_ROOT_PATH.'/web/'.$card->folder();

        $this->assertSame([basename($card->path())], array_values(array_diff(scandir($folder), ['.', '..'])));

        // Gone behind the plugin's back: still known to be there until it's
        // deleted through the plugin.
        unlink(CRAFT_ROOT_PATH.'/web/'.$card->path());
        $this->assertTrue($card->exists());
        $this->plugin()->output->delete($card->path());
        $this->assertFalse($card->exists());
    }

    public function test_an_entry_with_no_images_yet_lists_no_folder(): void
    {
        $this->template();
        $this->useTemplate('basic', ['journal']);
        $this->settings(['generateOnSave' => false]);

        $this->assertSame([], $this->plugin()->cards->for($this->entry())->files());
    }

    public function test_removing_an_entry_from_a_site_removes_that_sites_images(): void
    {
        $this->template();
        $this->useTemplate('basic', ['journal']);
        $entry = $this->entry();
        $this->runQueue();
        $card = $this->plugin()->cards->for($entry);
        $this->assertTrue($card->exists());

        Craft::$app->getElements()->trigger(Elements::EVENT_AFTER_DELETE_FOR_SITE, new ElementEvent(['element' => $entry]));

        $this->assertFalse($card->exists());
    }

    public function test_asking_while_its_rendering_doesnt_queue_it_again(): void
    {
        $this->template();
        $this->useTemplate('basic', ['journal']);
        $this->settings(['generateOnSave' => false]);
        $entry = $this->entry();
        $card = $this->plugin()->cards->for($entry);
        $this->assertTrue(GenerateImage::queue($card));

        $camera = new class extends FakeCamera
        {
            public ?Card $card = null;

            public ?bool $asked = null;

            public function capture(string $html, int $width, int $height): string
            {
                $this->asked = GenerateImage::queue($this->card);

                return self::PNG;
            }
        };
        $camera->card = $card;
        $this->plugin()->set('camera', $camera);

        $this->runQueue();

        $this->assertFalse($camera->asked);
        // And once it's done, it can be queued again.
        $this->assertTrue(GenerateImage::queue($card, force: true));
    }

    public function test_a_filesystem_with_no_url_falls_back(): void
    {
        $fallback = $this->asset('fallback.png');
        $this->template();
        $this->useTemplate('basic', ['journal']);
        $this->settings(['fallbackImageId' => $fallback->id]);
        $entry = $this->entry();
        $this->runQueue();

        $output = $this->plugin()->output;
        $fs = new Local(['path' => CRAFT_ROOT_PATH.'/web', 'hasUrls' => true, 'url' => '$UNSET_SOCIAL_IMAGES_URL']);
        (new \ReflectionProperty($output, 'fs'))->setValue($output, $fs);

        try {
            $this->assertSame('fallback', $this->plugin()->cards->image($entry)['source']);
        } finally {
            (new \ReflectionProperty($output, 'fs'))->setValue($output, null);
        }
    }
}
