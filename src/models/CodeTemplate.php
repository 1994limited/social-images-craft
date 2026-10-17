<?php

namespace nineteenninetyfour\socialimages\models;

use Craft;
use craft\elements\Entry;
use craft\web\View;
use NineteenNinetyFour\SocialImages\Core\Rendering\Page;
use NineteenNinetyFour\SocialImages\Core\Rendering\Tokens;
use NineteenNinetyFour\SocialImages\Core\Templates\Schema;
use nineteenninetyfour\socialimages\base\Template;
use nineteenninetyfour\socialimages\support\InSite;
use Throwable;

/**
 * A code template: a Twig template in the site's templates folder, listed in
 * the config's `views`.
 *
 * It gets `entry` and `socialLayout` (handle, width and height). It should
 * load nothing over the network: craft.socialImages.font() and
 * craft.socialImages.embed() inline fonts and pictures. See
 * docs/code-templates.md.
 */
class CodeTemplate implements Template
{
    public function __construct(
        protected string $handle,
        protected array $config,
    ) {}

    public function handle(): string
    {
        return $this->handle;
    }

    public function title(): string
    {
        return (string) ($this->config['title'] ?? Tokens::headline($this->handle));
    }

    public function kind(): string
    {
        return 'code';
    }

    public function template(): string
    {
        return (string) ($this->config['template'] ?? '');
    }

    public function layouts(): array
    {
        $layouts = [];

        foreach ((array) ($this->config['layouts'] ?? []) as $handle => $size) {
            $layouts[(string) $handle] = [
                'width' => max(200, min(2400, (int) ($size['width'] ?? $size[0] ?? 1200))),
                'height' => max(200, min(2400, (int) ($size['height'] ?? $size[1] ?? 630))),
            ];
        }

        $base = $layouts[Schema::BASE_LAYOUT] ?? ['width' => 1200, 'height' => 630];

        return [Schema::BASE_LAYOUT => $base] + $layouts;
    }

    public function hasLayout(string $layout): bool
    {
        return isset($this->layouts()[$layout]);
    }

    public function html(?Entry $entry, string $layout): string
    {
        $size = $this->layouts()[$layout] ?? $this->layouts()[Schema::BASE_LAYOUT];

        $html = InSite::run($entry, fn () => Craft::$app->getView()->renderTemplate($this->template(), [
            'entry' => $entry,
            'sample' => ['title' => Tokens::SAMPLES['title']],
            'socialLayout' => ['handle' => $layout, ...$size],
        ], View::TEMPLATE_MODE_SITE));

        return (new Page)->view($html);
    }

    /**
     * The template's source, the entry's field values, title, URI and date:
     * a code template can show anything about the entry, so any change to
     * it counts. Templates it includes, and elements it reaches through
     * relations, aren't read: change `version` in the config to remake its
     * images after changing those.
     */
    public function fingerprint(Entry $entry, string $layout): string
    {
        return substr(sha1((string) json_encode([
            $this->source(),
            $this->config['version'] ?? null,
            $this->layouts()[$layout] ?? null,
            $layout,
            $entry->getSerializedFieldValues(),
            $entry->title,
            $entry->uri,
            $entry->siteId,
            $entry->postDate?->getTimestamp(),
            Page::RUNTIME_VERSION,
        ])), 0, 12);
    }

    protected function source(): string
    {
        try {
            $path = Craft::$app->getView()->resolveTemplate($this->template(), View::TEMPLATE_MODE_SITE);

            return $path ? (string) sha1_file($path) : '';
        } catch (Throwable) {
            return '';
        }
    }
}
