<?php

namespace nineteenninetyfour\socialimages\models;

use craft\elements\Entry;
use nineteenninetyfour\socialimages\base\Template;
use NineteenNinetyFour\SocialImages\Core\Rendering\Page;
use NineteenNinetyFour\SocialImages\Core\Templates\Porter;
use NineteenNinetyFour\SocialImages\Core\Templates\Schema;
use nineteenninetyfour\socialimages\Plugin;
use nineteenninetyfour\socialimages\support\InSite;

/**
 * A template made in the designer: layouts and layers (see Schema), kept in
 * Project Config and drawn by the renderer.
 */
class DesignerTemplate implements Template
{
    /**
     * @param  array  $data  normalised by Schema::normalize()
     */
    public function __construct(
        protected string $handle,
        protected array $data,
        public readonly ?string $uid = null,
    ) {}

    public function handle(): string
    {
        return $this->handle;
    }

    public function title(): string
    {
        return $this->data['title'];
    }

    public function description(): string
    {
        return $this->data['description'] ?? '';
    }

    public function kind(): string
    {
        return 'designer';
    }

    public function layouts(): array
    {
        return array_map(fn ($layout) => ['width' => $layout['width'], 'height' => $layout['height']], $this->data['layouts']);
    }

    public function hasLayout(string $layout): bool
    {
        return isset($this->data['layouts'][$layout]);
    }

    public function layout(string $layout): array
    {
        return $this->data['layouts'][$layout] ?? $this->data['layouts'][Schema::BASE_LAYOUT];
    }

    public function html(?Entry $entry, string $layout): string
    {
        $layout = $this->layout($layout);

        return InSite::run($entry, fn () => (new Page)->designer(
            $layout,
            Plugin::getInstance()->resolver()->resolve($layout, $entry),
            $entry ? substr($entry->getSite()->language, 0, 2) : 'en',
        ));
    }

    public function fingerprint(Entry $entry, string $layout): string
    {
        return InSite::run($entry, fn () => Plugin::getInstance()->resolver()->fingerprint($this->layout($layout), $entry));
    }

    /**
     * @return array<int, string>
     */
    public function uploads(): array
    {
        return Porter::uploadsUsed($this->data);
    }

    /**
     * @return array<int, string>
     */
    public function families(): array
    {
        return Porter::families($this->data);
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
