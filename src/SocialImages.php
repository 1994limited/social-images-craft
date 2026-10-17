<?php

namespace nineteenninetyfour\socialimages;

use Closure;
use NineteenNinetyFour\SocialImages\Core\Rendering\Renderer;

/**
 * The way in for your own code: add a rendering driver from a module's
 * init() method.
 *
 *   SocialImages::extend('urlbox', fn (array $options) => new UrlboxRenderer($options));
 *
 * then choose it with SOCIAL_IMAGES_RENDERER=urlbox, giving its options in
 * config/social-images.php under renderer.drivers.urlbox.
 */
class SocialImages
{
    /**
     * @param  Closure(array<string, mixed>, \NineteenNinetyFour\SocialImages\Core\Rendering\RendererManager): Renderer  $factory
     */
    public static function extend(string $driver, Closure $factory): void
    {
        Plugin::getInstance()->renderers()->extend($driver, $factory);
    }

    /**
     * The renderer in use, or the named one.
     */
    public static function renderer(?string $driver = null): Renderer
    {
        return Plugin::getInstance()->renderer($driver);
    }
}
