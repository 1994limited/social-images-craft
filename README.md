# Social Images for Craft

Share images for every entry, made for you. Design a card once in the control panel, bind it to your entries' titles, photos, authors and dates, and Social Images makes a 1200 × 630 image for each entry when it's saved. Your `og:image` and `twitter:image` point at it, so links to your site look like they were designed one by one.

![Nine of the seventeen starter templates, each showing the same journal post](docs/images/gallery.png)

It works with SEOmatic, or with no SEO plugin at all, on any Craft 5 site.

## Features

- **A visual designer in the control panel.** Text, images, boxes and circles on a canvas. Drag, resize and snap them; undo anything. Gradients, shadows, borders, rounded corners, rotation, opacity.
- **Bound to your entries.** Text can show any field: `{title}`, `{subtitle ?? summary}`, `{author:name}`, `{date:j M Y}`, `{section:title}`, `{site:name}`. Pictures come from asset fields, with fallbacks, cropped around each asset's focal point.
- **Text that fits.** Each text layer shrinks between a largest and smallest size to fit its box, with an optional limit on lines.
- **Fonts.** Eight open-licence fonts come included. Add any Google Font (copied to your site once) or upload your own.
- **Preview with real entries.** Pick an entry and the canvas shows its title and photo. Render a real image from the designer.
- **Every size.** Open Graph (1200 × 630) plus square, portrait, Pinterest, story or any size, each with its own layout in the same template.
- **Deployed like the rest of your site.** Templates live in Project Config, beside your sections and fields.
- **Templates you can share.** Export a template as one JSON file, with its images and fonts inside. Import one by pasting it, uploading it, or giving its address. Seventeen starters come with the plugin, and templates move between Craft and Statamic.
- **Or write your own.** Developers can use a Twig template, with the same fonts, pictures and text fitting.
- **Made in the background, only when needed.** An image is made in the queue when an entry is saved (or a page first asks for it), and only if what it shows has changed. Pages never wait: until an image exists, they use the previous one or your fallback image.
- **Every site.** Each site gets its own image, in its own language.
- **In bulk.** Make the missing images, or remake them all, from the control panel or with `php craft social-images/generate`. Rate limits are waited out.
- **Per section, per entry.** Choose a template for each section (and site), let an entry choose another, or choose an image for an entry to use instead.
- **No Chrome needed on your server.** Images are rendered with Browsershot (Chrome on your server) or Cloudflare Browser Rendering (no Chrome at all), or a renderer of your own. `php craft social-images/doctor` checks it works.

| The designer | Every entry's image | In the entry |
| --- | --- | --- |
| ![The designer, editing the Editorial template with a real entry](docs/images/designer.png) | ![The Images page, listing each entry's images](docs/images/images.png) | ![The Social image field on a journal entry, with SEOmatic's preview beside it](docs/images/entry.png) |

The screenshots are from Dog-ear Books, a made-up bookshop. See [the demo](docs/demo.md).

## Requirements

- Craft CMS 5.6 or later, PHP 8.2 or later.
- One way to render images: Chrome or Chromium and Node.js on the server (Browsershot), or a Cloudflare account (Browser Rendering). See [Rendering](docs/rendering.md).

## Installation

```bash
composer require 1994/social-images-craft
php craft plugin/install social-images
```

Then choose how images are rendered (see [Rendering](docs/rendering.md)). For Browsershot, `composer require spatie/browsershot` and `npm install puppeteer`. For Cloudflare, add to `.env`:

```env
SOCIAL_IMAGES_RENDERER=cloudflare
CLOUDFLARE_API_TOKEN=your-token
CLOUDFLARE_ACCOUNT_ID=your-account-id
```

Check it works with `php craft social-images/doctor`. Make a template under **Social Images**, choose which sections use it under **Social Images → Settings**, and add the **Social image** field to your entry types. If you use SEOmatic, that's it. If not, add this to your layout's `<head>`:

```twig
{{ craft.socialImages.meta() }}
```

## Documentation

Start with [the documentation's contents](docs/README.md).

## Licence

Social Images is commercial software, sold through the [Craft Plugin Store](https://plugins.craftcms.com). You can try it free on local and staging sites; a licence is needed for each live site. See [LICENSE.md](LICENSE.md).

The included fonts are under the SIL Open Font License; each has its licence beside it in social-images-core's `resources/fonts`.

## Support

Email [hello@1994.co.uk](mailto:hello@1994.co.uk).

## Development

```bash
composer install
npm install
npm run build              # the control panel's scripts
cp tests/.env.example tests/.env
vendor/bin/codecept run    # the tests (needs a MySQL database, see tests/.env)
vendor/bin/pint            # code style
```
