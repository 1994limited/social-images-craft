<?php

namespace nineteenninetyfour\socialimages\tests\unit;

use Craft;
use craft\events\ConfigEvent;
use craft\web\View;

class TwigTest extends TestCase
{
    public function test_meta_tags_are_escaped(): void
    {
        $this->template();
        $this->useTemplate('basic', ['journal']);
        $entry = $this->entry('journal', 'Quotes " and <tags>');
        $this->runQueue();

        $html = Craft::$app->getView()->renderString('{{ craft.socialImages.meta(entry) }}', ['entry' => $entry], View::TEMPLATE_MODE_SITE);

        $this->assertStringContainsString('<meta property="og:image" content="http://social-images-craft.test/social-images/', $html);
        $this->assertStringContainsString('content="Quotes &quot; and &lt;tags&gt;"', $html);
        $this->assertStringContainsString('<meta name="twitter:card" content="summary_large_image">', $html);
    }

    public function test_a_code_template_renders_with_inlined_fonts_and_pictures(): void
    {
        $this->settings(['views' => ['jacket' => ['title' => 'Jacket', 'template' => 'social-images/jacket']]]);
        $this->plugin()->templates->handleRemoved(new ConfigEvent);
        $this->useTemplate('jacket', ['books']);

        $cover = $this->asset('cover.png', 200, 300);
        $entry = $this->entry('books', 'Coral Arithmetic', ['coverImage' => [$cover->id]]);
        $this->runQueue();

        $html = $this->camera->captures[0]['html'];

        $this->assertStringContainsString('<h1 data-fit="88">Coral Arithmetic</h1>', $html);
        $this->assertStringContainsString('@font-face{font-family:"Fraunces"', $html);
        $this->assertMatchesRegularExpression('#<img src="data:image/(webp|png);base64,#', $html);
        $this->assertStringContainsString('SocialImages.ready()', $html);
        $this->assertDoesNotMatchRegularExpression('#(src|href)=["\']?https?://#i', $html);
    }
}
