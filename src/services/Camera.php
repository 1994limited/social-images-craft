<?php

namespace nineteenninetyfour\socialimages\services;

use NineteenNinetyFour\SocialImages\Core\Rendering\RenderRequest;
use nineteenninetyfour\socialimages\Plugin;
use yii\base\Component;

/**
 * Takes the screenshot: an HTML page in, PNG bytes out, through the
 * renderer configured in config/social-images.php (see docs/rendering.md).
 */
class Camera extends Component
{
    public function capture(string $html, int $width, int $height): string
    {
        return Plugin::getInstance()->renderers()->driver()->render(new RenderRequest($html, $width, $height))->bytes;
    }
}
