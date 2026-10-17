<?php

namespace nineteenninetyfour\socialimages\integrations;

use Craft;

/**
 * Uses the social image for SEOmatic's og:image and twitter:image.
 */
class Seomatic
{
    public static function installed(): bool
    {
        return Craft::$app->getPlugins()->isPluginEnabled('seomatic');
    }

    public static function register(): void
    {
    }
}
