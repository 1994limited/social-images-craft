# PHP API

## An entry's image

```php
use nineteenninetyfour\socialimages\Plugin;

$cards = Plugin::getInstance()->cards;

$image = $cards->image($entry);            // or $cards->image($entry, 'square')
// ['url' => 'https://…/og-1c2d….png', 'width' => 1200, 'height' => 630, 'alt' => '…', 'source' => 'generated']
// or null when there's no image and no fallback
```

As with [Twig](tags.md#which-image), an image that hasn't been made yet gives the previous image or the fallback, and is queued.

To work with the generated file itself:

```php
$card = $cards->for($entry, 'og');   // null if the entry has no template or no URL, or the template hasn't that size

$card->exists();   // whether the current image has been made
$card->url();
$card->path();     // in the filesystem, e.g. social-images/{entry id}/{site id}/og-{fingerprint}.png
$card->render();   // make it now, through the renderer; returns the path
```

`$entry` is the entry in one site: each site's image is its own. `$cards->templateFor($entry)` is the template an entry uses, and `$cards->layoutsFor($entry)` the sizes made when it's saved.

## Queueing images

```php
use nineteenninetyfour\socialimages\jobs\GenerateImage;

GenerateImage::queue($card);                // or GenerateImage::queue($card, force: true)
```

It returns `false` without queueing when the same image is queued already, or failed a moment ago.

## Templates

```php
$templates = Plugin::getInstance()->templates;

$templates->all();                 // every template, designer and code, by handle
$templates->find('editorial');
$templates->save('editorial', $array);   // checked and normalised first; writes Project Config

$porter = Plugin::getInstance()->porter();
$json = json_encode($porter->export($templates->designer('editorial')->toArray()));
$template = $porter->import($porter->decode($json));
```

Saving writes Project Config, so do it only where admin changes are allowed. A template that isn't valid throws `NineteenNinetyFour\SocialImages\Core\Templates\InvalidTemplate`, whose message is written for the person importing it.

## Events

| Event | |
| --- | --- |
| `Templates::EVENT_AFTER_SAVE_TEMPLATE` | A designer template was saved or imported, or a Project Config change to one was applied (on a deploy, say). A `TemplateEvent`, with `$event->template`. |

Social Images already listens to it, to remake the images of entries using the template. To do something of your own as well:

```php
use nineteenninetyfour\socialimages\events\TemplateEvent;
use nineteenninetyfour\socialimages\services\Templates;
use yii\base\Event;

Event::on(Templates::class, Templates::EVENT_AFTER_SAVE_TEMPLATE, function (TemplateEvent $event) {
    Craft::info('Social image template changed: '.$event->template->handle(), 'my-module');
});
```

## Adding a renderer

A renderer takes a page and a size and returns an image. To render with another service, write a class that implements `NineteenNinetyFour\SocialImages\Core\Rendering\Renderer` and register it from a module's `init()` method, once plugins have loaded:

```php
use nineteenninetyfour\socialimages\SocialImages;

Craft::$app->onInit(function () {
    SocialImages::extend('urlbox', fn (array $options) => new UrlboxRenderer($options['key']));
});
```

Its options come from `renderer.drivers.urlbox` in `config/social-images.php`:

```php
<?php

use craft\helpers\App;

return [
    'renderer' => [
        'drivers' => [
            'urlbox' => ['key' => App::env('URLBOX_KEY')],
        ],
    ],
];
```

Read secrets there with `App::env()`. Choose it with `SOCIAL_IMAGES_RENDERER=urlbox`, then check it with `php craft social-images/doctor`.

The contract, the exceptions to throw (`RateLimited`, `QuotaExhausted`, `NotConfigured`, `RenderFailed`) and a PHPUnit trait that tests a driver against it are in social-images-core's [docs/drivers.md](https://github.com/1994limited/social-images-core/blob/main/docs/drivers.md). The page signals it's ready by setting `data-social-ready` on `<html>`: wait for `html[data-social-ready]` before taking the picture. A driver written for Social Images for Statamic works here as it is.

`SocialImages::renderer()` returns the renderer in use (or a named one, `SocialImages::renderer('cloudflare')`).
