<?php

namespace nineteenninetyfour\socialimages\tests\unit;

use Craft;

class ValuesTest extends TestCase
{
    protected function text(string $text, $entry): string
    {
        return $this->plugin()->tokens()->text($text, $entry);
    }

    public function test_entry_tokens(): void
    {
        $author = $this->user('Mira Castellane');
        $entry = $this->entry('journal', 'Autumn <em>reading</em>', ['excerpt' => "Short days,\n\nlong   books."], ['authorIds' => [$author->id]]);

        $this->assertSame('Autumn reading', $this->text('{title}', $entry));
        $this->assertSame('Short days, long books.', $this->text('{excerpt}', $entry));
        $this->assertSame('Mira Castellane', $this->text('{author}', $entry));
        $this->assertSame('Mira Castellane', $this->text('{author:name}', $entry));
        $this->assertSame('Journal', $this->text('{section:title}', $entry));
        // Templates from Statamic say collection.
        $this->assertSame('Journal', $this->text('{collection:title}', $entry));
        $this->assertSame('journal', $this->text('{collection:handle}', $entry));
        $this->assertSame('2 Oct 2026', $this->text('{date:j M Y}', $entry));
        $this->assertSame('Dog-ear Books', $this->text('{site:name}', $entry));
        $this->assertSame('social-images-craft.test', $this->text('{domain}', $entry));
        $this->assertStringContainsString('journal/'.$entry->slug, $this->text('{url}', $entry));
        $this->assertSame('Fallback', $this->text('{subtitle ?? "Fallback"}', $entry));
    }

    public function test_snake_case_handles_find_camel_case_fields(): void
    {
        $entry = $this->entry('books', 'Coral', ['byline' => 'Dev Okafor', 'price' => '13.00']);

        $this->assertSame('Dev Okafor', $this->text('{byline}', $entry));
        $this->assertSame('13.00', $this->text('{field:price}', $entry));
    }

    public function test_only_fields_and_safe_attributes_can_be_read(): void
    {
        $author = $this->user('Mira Castellane', ['email' => 'mira-private@example.com']);
        $entry = $this->entry('journal', 'Hello', [], ['authorIds' => [$author->id]]);

        $this->assertSame('', $this->text('{author:email}', $entry));
        $this->assertSame('', $this->text('{author.password}', $entry));
        $this->assertSame('', $this->text('{author:lastLoginAttemptIp}', $entry));
        $this->assertSame('', $this->text('{dateCreated}', $entry));
        $this->assertSame('Mira', explode(' ', $this->text('{author:fullName}', $entry))[0]);
    }

    public function test_samples_without_an_entry(): void
    {
        $this->assertSame('Your entry’s title shows here', $this->text('{title}', null));
        $this->assertSame('Articles', $this->text('{collection:title}', null));
        $this->assertSame('Featured Image', $this->text('{featured_image}', null));
    }

    public function test_dates_are_in_the_entrys_language(): void
    {
        $entry = $this->entry();
        $site = $entry->getSite();
        $language = $site->language;
        $site->language = 'fr-FR';

        try {
            $html = $this->plugin()->templates->save('dated', [
                'schema' => 'social-images/1',
                'title' => 'Dated',
                'layouts' => ['og' => ['layers' => [['id' => 't', 'type' => 'text', 'text' => '{date:j F Y}']]]],
            ])->html($entry, 'og');

            $this->assertStringContainsString('2 octobre 2026', $this->pageData($html));
            $this->assertSame('en', substr(Craft::$app->language, 0, 2));
        } finally {
            $site->language = $language;
        }
    }

    public function test_the_fingerprint_is_the_same_whatever_language_the_request_is_in(): void
    {
        $this->template(layers: [['id' => 't', 'type' => 'text', 'text' => '{date}']]);
        $this->useTemplate('basic', ['journal']);
        $this->settings(['generateOnSave' => false]);
        $entry = $this->entry();
        $site = $entry->getSite();
        $language = $site->language;
        $site->language = 'de-DE';

        try {
            $german = $this->plugin()->cards->for($entry)->hash();

            Craft::$app->set('formatter', Craft::$app->getI18n()->getLocaleById('en-GB')->getFormatter());
            $this->assertSame($german, $this->plugin()->cards->for($entry)->hash());

            Craft::$app->set('formatter', Craft::$app->getI18n()->getLocaleById('fr-FR')->getFormatter());
            $this->assertSame($german, $this->plugin()->cards->for($entry)->hash());
            $this->assertStringContainsString('Oktober', $this->pageData($this->plugin()->templates->designer('basic')->html($entry, 'og')));
        } finally {
            $site->language = $language;
            Craft::$app->set('formatter', Craft::$app->getI18n()->getLocaleById('en-GB')->getFormatter());
        }
    }
}
