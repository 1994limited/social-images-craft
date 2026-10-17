<?php

namespace nineteenninetyfour\socialimages\services;

use Craft;
use craft\elements\Asset;
use craft\elements\Entry;
use nineteenninetyfour\socialimages\base\Template;
use NineteenNinetyFour\SocialImages\Core\Templates\Schema;
use nineteenninetyfour\socialimages\fields\SocialImage;
use nineteenninetyfour\socialimages\images\Card;
use nineteenninetyfour\socialimages\jobs\GenerateImage;
use nineteenninetyfour\socialimages\models\SocialImageValue;
use nineteenninetyfour\socialimages\Plugin;
use yii\base\Component;
use yii\base\InvalidConfigException;

/**
 * Which image an entry gets, and where to find it.
 *
 * In order: the image chosen in the entry's Social image field; the image
 * generated from its template (or, while a new one is being made, the one
 * it replaces); and for pages with no image yet, the fallback image from
 * the settings.
 */
class Cards extends Component
{
    /**
     * Only entries with a URL are shared, so only they get images. Drafts,
     * revisions and nested entries don't.
     */
    public function supports(Entry $entry): bool
    {
        return $entry->id !== null
            && ! $entry->getIsDraft()
            && ! $entry->getIsRevision()
            && $entry->sectionId !== null
            && $entry->getUrl() !== null;
    }

    /**
     * The entry's Social image field, if its layout has one.
     */
    public function field(Entry $entry): ?SocialImage
    {
        foreach ($entry->getFieldLayout()?->getCustomFields() ?? [] as $field) {
            if ($field instanceof SocialImage) {
                return $field;
            }
        }

        return null;
    }

    public function value(Entry $entry): ?SocialImageValue
    {
        $field = $this->field($entry);
        $value = $field ? $entry->getFieldValue($field->handle) : null;

        return $value instanceof SocialImageValue ? $value : null;
    }

    public function templateFor(Entry $entry): ?Template
    {
        $chosen = $this->value($entry)?->template;
        $templates = Plugin::getInstance()->templates;

        if ($chosen === 'none') {
            return null;
        }

        if ($chosen && $template = $templates->find($chosen)) {
            return $template;
        }

        return $templates->find($this->rule($entry)['template'] ?? null);
    }

    /**
     * The layouts made when the entry is saved: the base layout, and the
     * extras its settings rule names that the template has.
     *
     * @return array<int, string>
     */
    public function layoutsFor(Entry $entry): array
    {
        $template = $this->templateFor($entry);

        if (! $template) {
            return [];
        }

        $layouts = array_unique([Schema::BASE_LAYOUT, ...($this->rule($entry)['layouts'] ?? [])]);

        return array_values(array_filter($layouts, fn ($layout) => $template->hasLayout($layout)));
    }

    public function for(Entry $entry, string $layout = Schema::BASE_LAYOUT): ?Card
    {
        if (! $this->supports($entry) || ! $template = $this->templateFor($entry)) {
            return null;
        }

        return $template->hasLayout($layout) ? new Card($entry, $template, $layout) : null;
    }

    /**
     * The image chosen in the entry's field, to use instead of a generated one.
     */
    public function override(Entry $entry): ?Asset
    {
        return $this->value($entry)?->getImage();
    }

    /**
     * The share image for a page, in the shape meta tags need. An entry
     * whose image hasn't been made yet gets the fallback this time, and its
     * image is queued: the page never waits for a render.
     *
     * @return array{url: string, width: ?int, height: ?int, alt: string, source: string}|null
     */
    public function image(?Entry $entry, string $layout = Schema::BASE_LAYOUT): ?array
    {
        if ($entry && ($override = $this->override($entry)) && $url = $override->getUrl()) {
            return [
                'url' => $url,
                'width' => $override->getWidth() ?: null,
                'height' => $override->getHeight() ?: null,
                'alt' => (string) ($override->alt ?: $entry->title),
                'source' => 'override',
            ];
        }

        if ($entry && $card = $this->for($entry, $layout)) {
            try {
                if ($card->exists()) {
                    return $this->generated($card, $card->url());
                }

                if (Plugin::getInstance()->getSettings()->enabled) {
                    GenerateImage::queue($card);
                }

                // Until the new one is made, the image it replaces.
                if ($previous = $card->previous()) {
                    return $this->generated($card, Plugin::getInstance()->output->url($previous));
                }
            } catch (InvalidConfigException $exception) {
                Craft::warning($exception->getMessage(), 'social-images');
            }
        }

        return $this->fallback();
    }

    /**
     * @return array{url: string, width: ?int, height: ?int, alt: string, source: string}|null
     */
    public function fallback(): ?array
    {
        $id = Plugin::getInstance()->getSettings()->fallbackImageId;
        $asset = $id ? Asset::find()->id($id)->one() : null;

        if (! $asset || ! $url = $asset->getUrl()) {
            return null;
        }

        return [
            'url' => $url,
            'width' => $asset->getWidth() ?: null,
            'height' => $asset->getHeight() ?: null,
            'alt' => (string) ($asset->alt ?: Craft::$app->getSites()->getCurrentSite()->getName()),
            'source' => 'fallback',
        ];
    }

    /**
     * Remove every image an entry has, in every site.
     */
    public function forget(Entry $entry): void
    {
        $output = Plugin::getInstance()->output;
        $output->deleteDirectory($output->directory().'/'.$entry->id);
    }

    /**
     * Remove an entry's images in one site, when it's taken out of it.
     */
    public function forgetSite(Entry $entry): void
    {
        $output = Plugin::getInstance()->output;
        $output->deleteDirectory($output->directory().'/'.$entry->id.'/'.$entry->siteId);
    }

    /**
     * The first settings rule for the entry's section and site.
     */
    public function rule(Entry $entry): ?array
    {
        $section = $entry->getSection()?->uid;
        $site = $entry->getSite()->uid;

        foreach (Plugin::getInstance()->getSettings()->templateRules() as $rule) {
            if ((! $rule['sections'] || in_array($section, $rule['sections'], true)) && (! $rule['sites'] || in_array($site, $rule['sites'], true))) {
                return $rule;
            }
        }

        return null;
    }

    protected function generated(Card $card, string $url): array
    {
        return [
            'url' => $url,
            'width' => $card->width(),
            'height' => $card->height(),
            'alt' => (string) $card->entry->title,
            'source' => 'generated',
        ];
    }
}
