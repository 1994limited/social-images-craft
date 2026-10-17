<?php

namespace nineteenninetyfour\socialimages\support;

use NineteenNinetyFour\SocialImages\Core\Rendering\Diagnosis;
use nineteenninetyfour\socialimages\Plugin;
use Throwable;

/**
 * A test render, for the doctor command and the settings page's Test
 * button. A renderer that can't even be made (unknown, or written for
 * another contract version) is reported the same way as one that can't
 * render.
 */
class Diagnose
{
    /**
     * @return array{driver: string, ok: bool, message: string, milliseconds: ?int, fix: ?string, capabilities: ?array}
     */
    public static function driver(?string $name = null): array
    {
        $renderers = Plugin::getInstance()->renderers();
        $name ??= $renderers->defaultDriver();

        try {
            $renderer = $renderers->driver($name);
        } catch (Throwable $exception) {
            return Diagnosis::failed($name, $exception)->toArray() + ['capabilities' => null];
        }

        $capabilities = $renderer->capabilities();

        return $renderer->check()->toArray() + ['capabilities' => ['formats' => $capabilities->formats, 'remote' => $capabilities->remote]];
    }
}
