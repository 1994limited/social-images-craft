<?php

namespace nineteenninetyfour\socialimages\variables;

use Craft;
use craft\elements\Asset;
use craft\elements\db\ElementQueryInterface;
use craft\elements\Entry;
use craft\helpers\Html;
use NineteenNinetyFour\SocialImages\Core\Templates\Schema;
use nineteenninetyfour\socialimages\Plugin;
use Twig\Markup;

/**
 * craft.socialImages, for site templates and code templates.
 *
 *   {{ craft.socialImages.meta(entry) }}        og:image and twitter:image tags
 *   {{ craft.socialImages.url(entry) }}         the image's URL
 *   {% set image = craft.socialImages.image(entry, 'square') %}
 *
 * In code templates:
 *
 *   {{ craft.socialImages.font('Inter', 600) }}           an inlined @font-face
 *   <img src="{{ craft.socialImages.embed(asset, 320, 480) }}">
 */
class SocialImagesVariable
{
    /**
     * @return array{url: string, width: ?int, height: ?int, alt: string, source: string}|null
     */
    public function image(?Entry $entry = null, string $layout = Schema::BASE_LAYOUT): ?array
    {
        return Plugin::getInstance()->cards->image($entry ?? $this->currentEntry(), $layout);
    }

    public function url(?Entry $entry = null, string $layout = Schema::BASE_LAYOUT): ?string
    {
        return $this->image($entry, $layout)['url'] ?? null;
    }

    public function meta(?Entry $entry = null): Markup
    {
        $image = $this->image($entry);

        if (! $image) {
            return new Markup('', 'UTF-8');
        }

        $tags = [Html::tag('meta', '', ['property' => 'og:image', 'content' => $image['url']])];

        if ($image['width'] && $image['height']) {
            $tags[] = Html::tag('meta', '', ['property' => 'og:image:width', 'content' => $image['width']]);
            $tags[] = Html::tag('meta', '', ['property' => 'og:image:height', 'content' => $image['height']]);
        }

        if ($image['alt'] !== '') {
            $tags[] = Html::tag('meta', '', ['property' => 'og:image:alt', 'content' => $image['alt']]);
        }

        $tags[] = Html::tag('meta', '', ['name' => 'twitter:card', 'content' => 'summary_large_image']);
        $tags[] = Html::tag('meta', '', ['name' => 'twitter:image', 'content' => $image['url']]);

        return new Markup(implode("\n", $tags), 'UTF-8');
    }

    /**
     * @font-face rules for a family at one weight and style, with the font
     * files inlined. For a code template's <style>.
     */
    public function font(string $family, int $weight = 400, string $style = 'normal'): Markup
    {
        $css = '';

        foreach (Plugin::getInstance()->fonts()->embed([['family' => $family, 'weight' => $weight, 'style' => $style === 'italic' ? 'italic' : 'normal']]) as $face) {
            $css .= sprintf(
                "@font-face{font-family:%s;font-style:%s;font-weight:%s;src:url(%s);%s}\n",
                json_encode($face['family']),
                $face['style'],
                $face['weight'],
                $face['src'],
                $face['unicode_range'] ? 'unicode-range:'.$face['unicode_range'].';' : '',
            );
        }

        return new Markup($css, 'UTF-8');
    }

    /**
     * An asset (or the first in a query) as a data URI, cropped around its
     * focal point to this size, or fitted with fit: 'contain'. For a code
     * template's <img src>.
     */
    public function embed(mixed $asset, int $width, int $height, string $fit = 'cover'): ?string
    {
        if ($asset instanceof ElementQueryInterface) {
            $asset = $asset->one();
        }

        return $asset instanceof Asset ? Plugin::getInstance()->images()->dataUri($asset, $width, $height, $fit) : null;
    }

    protected function currentEntry(): ?Entry
    {
        $element = Craft::$app->getUrlManager()->getMatchedElement();

        return $element instanceof Entry ? $element : null;
    }
}
