<?php

namespace nineteenninetyfour\socialimages\base;

use craft\elements\Entry;

/**
 * Something an image can be drawn from: a template made in the designer
 * (DesignerTemplate) or a Twig template (CodeTemplate).
 */
interface Template
{
    public function handle(): string;

    public function title(): string;

    /**
     * "designer" or "code".
     */
    public function kind(): string;

    /**
     * Each layout's size, by handle, the base layout (og) first.
     *
     * @return array<string, array{width: int, height: int}>
     */
    public function layouts(): array;

    public function hasLayout(string $layout): bool;

    /**
     * The page the screenshot is taken of. Self-contained: fonts and images
     * are inlined, so it loads nothing over the network.
     */
    public function html(?Entry $entry, string $layout): string;

    /**
     * A hash of everything that changes this entry's image in this layout.
     * Unchanged means the image already made is still right.
     */
    public function fingerprint(Entry $entry, string $layout): string;
}
