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
        // Made once per request in the request's language, so it's swapped
        // too: otherwise dates (and so fingerprints) would differ between
        // the control panel, the queue and the site.
        $previousFormatter = Craft::$app->getFormatter();

        $sites->setCurrentSite($site);
        Craft::$app->language = $site->language;
        Craft::$app->set('formatter', Craft::$app->getI18n()->getLocaleById($site->language)->getFormatter());

        try {
            return $callback();
        } finally {
            $sites->setCurrentSite($previousSite);
            Craft::$app->language = $previousLanguage;
            Craft::$app->set('formatter', $previousFormatter);
        }
    }
}
