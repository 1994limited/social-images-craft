<?php

namespace nineteenninetyfour\socialimages\tests\unit;

use Craft;
use craft\events\ConfigEvent;
use NineteenNinetyFour\SocialImages\Core\Templates\InvalidTemplate;
use nineteenninetyfour\socialimages\models\CodeTemplate;

class TemplatesTest extends TestCase
{
    public function test_templates_are_kept_in_project_config(): void
    {
        $template = $this->template('editorial');
        $stored = Craft::$app->getProjectConfig()->get('socialImages.templates.'.$template->uid);

        $this->assertSame('editorial', $stored['handle']);
        $this->assertSame('Editorial', json_decode($stored['data'], true)['title']);

        $this->plugin()->templates->delete('editorial');
        $this->assertNull($this->plugin()->templates->find('editorial'));
        $this->assertNull(Craft::$app->getProjectConfig()->get('socialImages.templates.'.$template->uid));
    }

    public function test_whats_saved_is_checked_like_any_import(): void
    {
        $template = $this->plugin()->templates->save('checked', [
            'title' => 'Checked',
            'layouts' => ['og' => ['layers' => [['id' => 't', 'type' => 'text', 'text' => '{title}', 'onload' => 'alert(1)', 'color' => 'red;}</style>']]]],
        ]);

        $layer = $template->layout('og')['layers'][0];
        $this->assertArrayNotHasKey('onload', $layer);
        $this->assertStringNotContainsString('<', json_encode($template->toArray()));

        $this->expectException(InvalidTemplate::class);
        $this->plugin()->templates->save('Bad Handle!', ['title' => 'x']);
    }

    public function test_handles_are_made_unique(): void
    {
        $this->template('summer_sale');

        $this->assertSame('summer_sale_2', $this->plugin()->templates->uniqueHandle('Summer Sale'));
    }

    public function test_code_templates_come_from_the_config(): void
    {
        $this->settings(['views' => ['jacket' => ['title' => 'Jacket', 'template' => 'social-images/jacket', 'layouts' => ['square' => [1080, 1080]]]]]);
        $this->plugin()->templates->handleRemoved(new ConfigEvent);

        $template = $this->plugin()->templates->find('jacket');

        $this->assertInstanceOf(CodeTemplate::class, $template);
        $this->assertSame(['og', 'square'], array_keys($template->layouts()));

        $this->expectException(InvalidTemplate::class);
        $this->plugin()->templates->save('jacket', ['title' => 'Clash']);
    }

    public function test_saving_a_template_queues_its_images_to_be_remade(): void
    {
        $this->template();
        $queue = Craft::$app->getQueue();
        $before = $queue->getTotalJobs();

        $this->template(layers: [['id' => 't', 'type' => 'text', 'text' => 'Changed']]);

        $this->assertSame($before + 1, $queue->getTotalJobs());
    }

    public function test_remaking_queues_only_entries_that_had_an_image(): void
    {
        $this->template();
        $this->useTemplate('basic', ['journal']);

        $made = $this->entry('journal', 'Made');
        $this->runQueue();
        $this->settings(['generateOnSave' => false]);
        $never = $this->entry('journal', 'Never');

        $this->template(layers: [['id' => 't', 'type' => 'text', 'text' => '{title}!']]);
        $this->camera->captures = [];
        $this->runQueue();

        $this->assertCount(1, $this->camera->captures);
        $this->assertStringContainsString('Made!', $this->pageData($this->camera->captures[0]['html']));
        $this->assertFalse($this->plugin()->cards->for($never)->exists());
    }

    public function test_starters_come_from_the_core_package(): void
    {
        $starters = $this->plugin()->templates->starters();

        $this->assertArrayHasKey('editorial', $starters);
        $this->assertGreaterThanOrEqual(17, count($starters));
    }
}
