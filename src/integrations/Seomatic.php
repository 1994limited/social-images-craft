<?php

namespace nineteenninetyfour\socialimages\integrations;

use Craft;
use craft\elements\Entry;
use craft\events\TemplateEvent;
use craft\web\View;
use nineteenninetyfour\socialimages\Plugin;
use nystudio107\seomatic\helpers\DynamicMeta;
use nystudio107\seomatic\Seomatic as SeomaticPlugin;
use Throwable;
use yii\base\Event;

/**
 * Uses the social image for SEOmatic's og:image and twitter:image.
 *
 * SEOmatic's own og:image and twitter:image follow its SEO image unless
 * they're set apart, so the social image goes in as the SEO image, with
 * its size and alt text, when SEOmatic adds a page's dynamic meta. With the
 * "replace" setting it's used only while the page's SEO image is the
 * site-wide one, so an entry or section with an image of its own keeps it.
 *
 * SEOmatic caches each page's meta, so when a new image is made, its
 * section's cache is cleared.
 */
class Seomatic
{
    public static function installed(): bool
    {
        return Craft::$app->getPlugins()->isPluginEnabled('seomatic') && class_exists(SeomaticPlugin::class);
    }

    public static function register(): void
    {
        if (! class_exists(DynamicMeta::class)) {
            return;
        }

        Event::on(DynamicMeta::class, DynamicMeta::EVENT_ADD_DYNAMIC_META, fn () => self::safely());

        // The entry sidebar's SEO preview loads SEOmatic's meta without its
        // dynamic meta, so the image goes in just before it's drawn.
        Event::on(View::class, View::EVENT_BEFORE_RENDER_TEMPLATE, function (TemplateEvent $event) {
            if (str_starts_with($event->template, 'seomatic/_sidebars/') && str_ends_with($event->template, '-preview.twig')) {
                self::safely();
            }
        });
    }

    protected static function safely(): void
    {
        try {
            self::apply();
        } catch (Throwable $exception) {
            Craft::warning('Social Images couldn’t set SEOmatic’s image: '.$exception->getMessage(), 'social-images');
        }
    }

    public static function apply(): void
    {
        $mode = Plugin::getInstance()->getSettings()->seomatic;
        $entry = SeomaticPlugin::$matchedElement ?? null;

        if ($mode === 'off' || ! $entry instanceof Entry) {
            return;
        }

        $vars = SeomaticPlugin::$plugin->metaContainers->metaGlobalVars;

        if (! $vars || ($mode === 'replace' && self::hasOwnImage($vars->seoImage, $entry->siteId))) {
            return;
        }

        $image = Plugin::getInstance()->cards->image($entry);

        // Only the entry's own image: the fallback doesn't replace SEOmatic's.
        if (! $image || $image['source'] === 'fallback') {
            return;
        }

        $vars->seoImage = $image['url'];
        $vars->seoImageWidth = $image['width'] ? (string) $image['width'] : '';
        $vars->seoImageHeight = $image['height'] ? (string) $image['height'] : '';
        $vars->seoImageDescription = $image['alt'];
    }

    /**
     * Whether the page's SEO image is its own, not the site-wide default.
     */
    protected static function hasOwnImage(mixed $seoImage, int $siteId): bool
    {
        if ($seoImage === null || $seoImage === '') {
            return false;
        }

        $global = SeomaticPlugin::$plugin->metaBundles->getGlobalMetaBundle($siteId)?->metaGlobalVars?->seoImage;

        return $seoImage !== $global;
    }

    /**
     * A new image for this entry: SEOmatic's cached meta for its section
     * may hold the old one.
     */
    public static function forget(Entry $entry): void
    {
        if (! self::installed() || ! $entry->sectionId) {
            return;
        }

        try {
            SeomaticPlugin::$plugin->metaContainers->invalidateContainerCacheById($entry->sectionId, 'section', $entry->siteId);
        } catch (Throwable) {
        }
    }
}
