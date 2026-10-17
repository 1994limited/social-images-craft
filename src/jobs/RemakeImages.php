<?php

namespace nineteenninetyfour\socialimages\jobs;

use Craft;
use craft\elements\Entry;
use craft\queue\BaseJob;
use nineteenninetyfour\socialimages\Plugin;

/**
 * After a template changes, queues a render for each entry that uses it
 * and whose image is now out of date. Old images stay in use until each new
 * one is ready.
 */
class RemakeImages extends BaseJob
{
    public string $template;

    public function execute($queue): void
    {
        $cards = Plugin::getInstance()->cards;
        $query = Entry::find()->site('*')->status(null)->sectionId(':notempty:');
        $total = max(1, (int) $query->count());
        $done = 0;

        foreach ($query->each(100) as $entry) {
            /** @var Entry $entry */
            $this->setProgress($queue, ++$done / $total);

            if (! $cards->supports($entry) || $cards->templateFor($entry)?->handle() !== $this->template) {
                continue;
            }

            foreach ($cards->layoutsFor($entry) as $layout) {
                $card = $cards->for($entry, $layout);

                if ($card && ! $card->exists() && $card->previous()) {
                    GenerateImage::queue($card);
                }
            }
        }
    }

    protected function defaultDescription(): ?string
    {
        return Craft::t('social-images', 'Updating social images for “{template}”', ['template' => $this->template]);
    }
}
