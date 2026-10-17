<?php

namespace nineteenninetyfour\socialimages\tests\unit;

use Craft;
use craft\elements\Entry;
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
        $this->assertStringContainsString('Hello world', $this->camera->captures[0]['html']);
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
}
