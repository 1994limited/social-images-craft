<?php

namespace nineteenninetyfour\socialimages\jobs;

use Craft;
use craft\elements\Entry;
use craft\queue\BaseJob;
use NineteenNinetyFour\SocialImages\Core\Templates\Schema;
use nineteenninetyfour\socialimages\images\Card;
use nineteenninetyfour\socialimages\Plugin;
use nineteenninetyfour\socialimages\support\RateLimits;
use Throwable;

/**
 * Renders an entry's image in one site and layout, and removes that
 * layout's older files.
 *
 * Queued once per entry, site, layout and fingerprint: a page viewed again
 * and again before its image exists queues one render, while an edit that
 * changes the image always gets a render of its own.
 */
class GenerateImage extends BaseJob
{
    public int $entryId;

    public int $siteId;

    public string $layout = Schema::BASE_LAYOUT;

    public bool $force = false;

    public ?string $hash = null;

    /** Refusals so far, for rate limits. */
    public int $attempt = 1;

    /**
     * Queue this card's render, unless it's queued already or failed a
     * moment ago.
     */
    public static function queue(Card $card, bool $force = false, ?int $delay = null): bool
    {
        $hash = $card->hash();

        // Remembered for as long as the job may wait, so page views meanwhile
        // don't queue it again.
        if (self::recentlyFailed($card, $hash) || ! Craft::$app->getCache()->add(self::queuedKey($card->key(), $hash), true, ($delay ?? 0) + 3600)) {
            return false;
        }

        $queue = Craft::$app->getQueue();

        if ($delay) {
            $queue->delay($delay);
        }

        $queue->push(new self([
            'entryId' => $card->entry->id,
            'siteId' => $card->entry->siteId,
            'layout' => $card->layout,
            'force' => $force,
            'hash' => $hash,
        ]));

        return true;
    }

    /**
     * A render that just failed isn't tried again from page views for a
     * few minutes (or until a used-up allowance resets), so a broken
     * template doesn't cost a render on every visit.
     */
    public static function recentlyFailed(Card $card, ?string $hash = null): bool
    {
        return (bool) Craft::$app->getCache()->get(self::failureKey($card->key(), $hash ?? $card->hash()));
    }

    public static function failureKey(string $key, string $hash): string
    {
        return 'social-images:failed:'.$key.':'.$hash;
    }

    protected static function queuedKey(string $key, string $hash): string
    {
        return 'social-images:queued:'.$key.':'.$hash;
    }

    public function execute($queue): void
    {
        $this->render();
    }

    /**
     * @return string|null the image's path, or null if there's none to make
     *
     * @throws Throwable
     */
    public function render(bool $queued = true): ?string
    {
        $entry = Entry::find()->id($this->entryId)->siteId($this->siteId)->status(null)->one();
        $card = $entry ? Plugin::getInstance()->cards->for($entry, $this->layout) : null;
        $cache = Craft::$app->getCache();

        if ($this->hash !== null && $card) {
            $cache->delete(self::queuedKey($card->key(), $this->hash));
        }

        if (! $card) {
            return null;
        }

        if (! $this->force && $card->exists()) {
            return $card->path();
        }

        // The entry has changed since this job was queued: the change queued
        // a job of its own for what it shows now, so this one stands down.
        if (! $this->force && $this->hash !== null && $this->hash !== $card->hash()) {
            return null;
        }

        try {
            $path = $card->render();
        } catch (Throwable $exception) {
            $resetsAt = RateLimits::resetsAt($exception);

            // On the queue, a refusal waits as long as the service asked, or
            // until the allowance resets, and tries again. It's still queued,
            // not failed, as far as pages and the control panel are concerned.
            if ($queued && RateLimits::caused($exception) && $this->attempt < Plugin::getInstance()->getSettings()->rateLimitAttempts) {
                $wait = $this->waitFor($exception, $resetsAt);
                $cache->set(self::queuedKey($card->key(), $card->hash()), true, $wait + 3600);

                Craft::$app->getQueue()->delay($wait)->push(new self([
                    'entryId' => $this->entryId,
                    'siteId' => $this->siteId,
                    'layout' => $this->layout,
                    'force' => $this->force,
                    'hash' => $this->hash,
                    'attempt' => $resetsAt ? $this->attempt : $this->attempt + 1,
                ]));

                return null;
            }

            $cache->set(self::failureKey($card->key(), $card->hash()), true, $resetsAt ? max(60, $resetsAt->getTimestamp() - time()) : 300);

            throw $exception;
        }

        $cache->delete(self::failureKey($card->key(), $card->hash()));

        return $path;
    }

    protected function waitFor(Throwable $exception, ?\DateTimeImmutable $resetsAt): int
    {
        if ($resetsAt) {
            return max(60, $resetsAt->getTimestamp() - time());
        }

        return RateLimits::retryAfter($exception) ?? Plugin::getInstance()->getSettings()->rateLimitWait * $this->attempt;
    }

    protected function defaultDescription(): ?string
    {
        return Craft::t('social-images', 'Making a social image');
    }
}
