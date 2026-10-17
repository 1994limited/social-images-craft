<?php

namespace nineteenninetyfour\socialimages\images;

use Craft;
use craft\elements\Entry;
use nineteenninetyfour\socialimages\base\Template;
use nineteenninetyfour\socialimages\integrations\Seomatic;
use nineteenninetyfour\socialimages\Plugin;
use nineteenninetyfour\socialimages\services\Output;
use Throwable;

/**
 * One entry's image, in one site and one layout, and the PNG file it's
 * kept in.
 *
 * The file name carries a fingerprint of everything that changes the
 * picture (see Template::fingerprint()), so an unchanged entry maps to a
 * file that already exists and a changed one to a file that doesn't. That
 * is the whole "does this need making" check.
 */
class Card
{
    protected ?string $hash = null;

    public function __construct(
        public readonly Entry $entry,
        public readonly Template $template,
        public readonly string $layout,
    ) {}

    public function hash(): string
    {
        return $this->hash ??= $this->template->fingerprint($this->entry, $this->layout);
    }

    /**
     * What tells this entry, site and layout apart in cache keys and locks.
     */
    public function key(): string
    {
        return $this->entry->id.':'.$this->entry->siteId.':'.$this->layout;
    }

    /**
     * The folder this entry's images are in for its site, e.g.
     * social-images/{entry id}/{site id}. Entries keep their id across
     * sites in Craft, so the site is part of it.
     */
    public function folder(): string
    {
        return $this->output()->directory().'/'.$this->entry->id.'/'.$this->entry->siteId;
    }

    /**
     * e.g. social-images/{entry id}/{site id}/og-{hash}.png
     */
    public function path(): string
    {
        return $this->folder().'/'.$this->layout.'-'.$this->hash().'.png';
    }

    public function width(): int
    {
        return $this->template->layouts()[$this->layout]['width'];
    }

    public function height(): int
    {
        return $this->template->layouts()[$this->layout]['height'];
    }

    public function exists(): bool
    {
        return $this->output()->exists($this->path());
    }

    public function url(): string
    {
        return $this->output()->url($this->path());
    }

    /**
     * The URL with the file's time appended, for the control panel: a
     * forced re-render keeps the file name, so without it the browser would
     * keep showing its cached copy.
     */
    public function previewUrl(): string
    {
        return $this->url().'?v='.$this->output()->lastModified($this->path());
    }

    /**
     * When the render now running started, if one is.
     */
    public function renderingSince(): ?int
    {
        $started = Craft::$app->getCache()->get($this->renderingKey());

        return is_int($started) ? $started : null;
    }

    protected function renderingKey(): string
    {
        return 'social-images:rendering:'.$this->key().':'.$this->hash();
    }

    /**
     * The previous image's preview URL, if there is one, to show while the
     * current one is made.
     */
    public function previousPreviewUrl(): ?string
    {
        $previous = $this->previous();

        return $previous ? $this->output()->url($previous).'?v='.$this->output()->lastModified($previous) : null;
    }

    /**
     * This layout's files, current or stale.
     *
     * @return array<int, string>
     */
    public function files(): array
    {
        $prefix = $this->folder().'/'.$this->layout.'-';

        return array_values(array_filter(
            $this->output()->files($this->folder()),
            fn (string $file) => str_starts_with($file, $prefix) && str_ends_with($file, '.png'),
        ));
    }

    /**
     * Render the image, save it and delete this layout's older files.
     */
    public function render(): string
    {
        $size = $this->template->layouts()[$this->layout];
        $cache = Craft::$app->getCache();

        // Marked while it runs, so the control panel can say it's rendering
        // (not just waiting in the queue).
        $cache->set($this->renderingKey(), time(), 300);

        try {
            $png = Plugin::getInstance()->camera->capture($this->template->html($this->entry, $this->layout), $size['width'], $size['height']);
        } finally {
            $cache->delete($this->renderingKey());
        }

        // One render at a time replaces this layout's files, and none
        // deletes the image that's current now: a slow render of an older
        // version can finish after a newer one.
        $mutex = Craft::$app->getMutex();
        $lock = 'social-images:write:'.$this->key();
        $locked = $mutex->acquire($lock, 10);

        try {
            $this->output()->put($this->path(), $png);

            if ($locked) {
                $current = $this->currentPath();

                foreach ($this->files() as $file) {
                    if ($file !== $this->path() && $file !== $current) {
                        $this->output()->delete($file);
                    }
                }
            }
        } finally {
            if ($locked) {
                $mutex->release($lock);
            }
        }

        Seomatic::forget($this->entry);

        return $this->path();
    }

    /**
     * The path the entry's image has now, as saved, which may have moved
     * on since this card was made.
     */
    protected function currentPath(): ?string
    {
        $entry = Entry::find()->id($this->entry->id)->siteId($this->entry->siteId)->status(null)->one();

        try {
            return $entry ? Plugin::getInstance()->cards->for($entry, $this->layout)?->path() : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * The newest earlier image for this layout, if there is one.
     */
    public function previous(): ?string
    {
        $files = array_filter($this->files(), fn (string $file) => $file !== $this->path());

        usort($files, fn ($a, $b) => $this->output()->lastModified($b) <=> $this->output()->lastModified($a));

        return $files[0] ?? null;
    }

    protected function output(): Output
    {
        return Plugin::getInstance()->output;
    }
}
