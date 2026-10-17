<?php

namespace nineteenninetyfour\socialimages\support;

use Closure;
use Craft;
use craft\elements\Entry;

/**
 * Runs a callback as the entry's own site, in its language, so dates,
 * translations and Twig's `currentSite` come out as on the entry's page.
 */
class InSite
{
    public static function run(?Entry $entry, Closure $callback): mixed
    {
        if (! $entry) {
            return $callback();
        }

        $sites = Craft::$app->getSites();
        $site = $entry->getSite();
        $previousSite = $sites->getCurrentSite();
        $previousLanguage = Craft::$app->language;

        if ($site->id === $previousSite->id && $site->language === $previousLanguage) {
            return $callback();
        }

        $sites->setCurrentSite($site);
        Craft::$app->language = $site->language;

        try {
            return $callback();
        } finally {
            $sites->setCurrentSite($previousSite);
            Craft::$app->language = $previousLanguage;
        }
    }
}
