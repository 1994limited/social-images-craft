# Configuration

Choices about your content are on the settings page, saved to Project Config; how the plugin runs is in `config/social-images.php` and `.env`.

## The settings page

**Social Images → Settings**, for admins. Saved to Project Config, so it deploys with your sections and fields, and can only be changed where admin changes are allowed.

| Setting | |
| --- | --- |
| Templates | The rules: which sections (and sites) use which template, and which extra sizes they make on save. See [Choosing templates](choosing-templates.md). |
| Generate when an entry is saved | On by default. Off: images are made when a page first asks for one, or from the Images page or the command. |
| Fallback image | An image asset for pages with no image yet. |
| Where images are saved | A filesystem and a folder. See [Where images are saved](#where-images-are-saved). |
| Rendering | Which renderer is in use, and a **Test** button that renders a test image. The renderer is chosen in `.env`, not here. |
| SEOmatic | When the social image is used for SEOmatic's tags. See [SEOmatic](seo.md). |

### Where images are saved

With no filesystem chosen, images go in your web root, in `web/social-images`. Or choose any filesystem with public URLs (a local one, S3, R2 and so on) and a folder in it. If its URL comes from an environment variable that isn't set, pages use the fallback image and a warning is logged.

Images go to `{folder}/{entry ID}/{site ID}/{size}-{fingerprint}.png`. Entries keep their ID across sites in Craft, so the site is part of the path. Images can be remade at any time, so the folder needn't be in your repository.

## `config/social-images.php`

Create the file to change these. Like any Craft config file, it can set values for every environment, or for some only:

```php
<?php

return [
    '*' => [
        'rateLimitWait' => 15,
    ],
    'staging' => [
        'enabled' => false,
    ],
];
```

It can also set the settings page's values (`rules`, `generateOnSave`, `fallbackImageId`, `imagesFs`, `imagesPath`, `seomatic`). A value set here wins over the settings page.

The settings below are set here only, and never saved to Project Config.

### Turning it off

```php
'enabled' => false,
```

Nothing is made on save, on page view, after a template changes or by the command. See [Turning it off](rendering.md#turning-it-off).

### Fonts and uploads

```php
'libraryPath' => '@root/social-images',
```

Where the designer keeps the fonts and pictures you add: `fonts/` (fonts you've added, with `fonts.json` listing them) and `uploads/` (pictures uploaded to templates). Commit this folder with your site. The templates themselves are in Project Config.

### Code templates

```php
'views' => [],
```

See [Code templates](code-templates.md).

### Rate limits

```php
'rateLimitWait' => 15,
'rateLimitAttempts' => 4,
```

How long queued renders and the command wait (in seconds, more each time) when the screenshot service says it's busy and doesn't say for how long, and after how many refusals in a row they stop. See [Limits](rendering.md#limits).

### Importing

```php
'importMaxKilobytes' => 10240,
'importTimeout' => 15,
```

The largest template that can be imported, pasted, from a file or from an address, and how many seconds to wait for one from an address.

### Rendering

```php
'renderer' => [
    'driver' => 'browsershot',
    'drivers' => [
        'browsershot' => [/* node_binary, npm_binary, chrome_path, node_modules_path, no_sandbox */],
        'cloudflare' => [/* api_token, account_id */],
    ],
],
```

Which renderer makes the images, and each one's options. You don't usually need this: each is read from `.env` (`SOCIAL_IMAGES_RENDERER`, `SOCIAL_IMAGES_NODE_BINARY`, `CLOUDFLARE_API_TOKEN` and the rest; see [Renderers](rendering.md#renderers)), and anything you set here is added on top. A renderer of your own takes its options from here (see [Adding a renderer](php-api.md#adding-a-renderer)). Read secrets with `App::env()`; never write them in the file.
