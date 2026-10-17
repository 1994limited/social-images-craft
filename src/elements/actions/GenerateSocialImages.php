<?php

namespace nineteenninetyfour\socialimages\elements\actions;

use Craft;
use craft\base\ElementAction;
use craft\elements\db\ElementQueryInterface;
use craft\elements\Entry;
use nineteenninetyfour\socialimages\jobs\GenerateImage;
use nineteenninetyfour\socialimages\Plugin;

/**
 * "Regenerate social images" on the entries index: queues a fresh render of
 * each selected entry's images.
 */
class GenerateSocialImages extends ElementAction
{
    public static function displayName(): string
    {
        return Craft::t('social-images', 'Regenerate social images');
    }

    public function getTriggerHtml(): ?string
    {
        return null;
    }

    public function performAction(ElementQueryInterface $query): bool
    {
        if (! Craft::$app->getUser()->checkPermission(Plugin::PERMISSION_MANAGE)) {
            $this->setMessage(Craft::t('social-images', 'You can’t regenerate social images.'));

            return false;
        }

        $cards = Plugin::getInstance()->cards;
        $queued = 0;

        foreach ($query->all() as $entry) {
            if (! $entry instanceof Entry) {
                continue;
            }

            foreach ($cards->layoutsFor($entry) as $layout) {
                if (($card = $cards->for($entry, $layout)) && GenerateImage::queue($card, force: true)) {
                    $queued++;
                }
            }
        }

        $this->setMessage(Craft::t('social-images', '{count, plural, =0{No images to make} =1{1 image queued} other{# images queued}}', ['count' => $queued]));

        return true;
    }
}
