<?php

namespace nineteenninetyfour\socialimages\tests\unit;

use Craft;
use craft\base\Element;
use craft\elements\Entry;

class FieldTest extends TestCase
{
    public function test_an_entry_can_choose_its_template_or_none(): void
    {
        $this->template('basic');
        $this->template('other');
        $this->useTemplate('basic', ['journal']);
        $this->settings(['generateOnSave' => false]);

        $cards = $this->plugin()->cards;

        $this->assertSame('basic', $cards->templateFor($this->entry())->handle());
        $this->assertSame('other', $cards->templateFor($this->entry('journal', 'Other', ['socialImage' => ['template' => 'other']]))->handle());
        $this->assertNull($cards->templateFor($this->entry('journal', 'None', ['socialImage' => ['template' => 'none']])));
        // An unknown handle falls back to the settings' choice.
        $this->assertSame('basic', $cards->templateFor($this->entry('journal', 'Gone', ['socialImage' => ['template' => 'gone']]))->handle());
    }

    public function test_an_image_chosen_in_the_field_is_used_instead(): void
    {
        $this->template();
        $this->useTemplate('basic', ['journal']);
        $this->settings(['generateOnSave' => false]);

        $photo = $this->asset('own.png');
        $entry = $this->entry('journal', 'Own', ['socialImage' => ['imageId' => [$photo->id]]]);
        $before = Craft::$app->getQueue()->getTotalJobs();
        $image = $this->plugin()->cards->image($entry);

        $this->assertSame('override', $image['source']);
        $this->assertSame($photo->getUrl(), $image['url']);
        $this->assertSame($before, Craft::$app->getQueue()->getTotalJobs());
    }

    public function test_the_fallback_image_is_used_until_an_image_is_made(): void
    {
        $fallback = $this->asset('fallback.png');
        $this->template();
        $this->useTemplate('basic', ['journal']);
        $this->settings(['generateOnSave' => false, 'fallbackImageId' => $fallback->id]);

        $image = $this->plugin()->cards->image($this->entry());

        $this->assertSame('fallback', $image['source']);
        $this->assertSame($fallback->getUrl(), $image['url']);
    }

    public function test_the_value_is_stored_tidily(): void
    {
        $entry = $this->entry('journal', 'Tidy', ['socialImage' => ['template' => '<script>', 'imageId' => 'x']]);
        $value = $entry->getFieldValue('socialImage');

        $this->assertNull($value->template);
        $this->assertNull($value->imageId);
    }

    public function test_the_image_chosen_must_be_one_the_field_offers(): void
    {
        $field = Craft::$app->getFields()->getFieldByHandle('socialImage');
        $sources = $field->sources;
        $field->sources = ['volume:not-this-one'];

        try {
            $photo = $this->asset('elsewhere.png');
            $section = Craft::$app->getEntries()->getSectionByHandle('journal');
            $entry = new Entry(['sectionId' => $section->id, 'typeId' => $section->getEntryTypes()[0]->id, 'title' => 'Sneaky']);
            $entry->setFieldValues(['socialImage' => ['imageId' => [$photo->id]]]);
            $entry->setScenario(Element::SCENARIO_LIVE);

            $this->assertFalse(Craft::$app->getElements()->saveElement($entry));
            $this->assertArrayHasKey('socialImage', $entry->getErrors());
        } finally {
            $field->sources = $sources;
        }
    }
}
