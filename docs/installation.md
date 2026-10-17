# Installation

## Requirements

- Craft CMS 5.6 or later, PHP 8.2 or later.
- A way to render images: [Browsershot or Cloudflare Browser Rendering](rendering.md).

## Installing

```bash
composer require 1994/social-images-craft
php craft plugin/install social-images
```

Then [set up rendering](rendering.md). On your own computer with Chrome installed, Browsershot is the quickest start; on a server without Chrome, use Cloudflare.

Images are saved to `web/social-images` unless you [choose another filesystem](configuration.md#where-images-are-saved).

## First steps

1. **Make a template.** Go to **Social Images → Templates** and choose **Create template**. Start from one of the seventeen starters and change what you like, or start blank. See [The designer](designer.md).

    Templates are kept in Project Config, like sections and fields, so make them where admin changes are allowed (usually your own computer) and deploy them as you deploy the rest. Commit the `social-images` folder at your project's root too: it holds the fonts and pictures you add in the designer.
2. **Choose who uses it.** In **Social Images → Settings**, add a rule: which sections (or all of them) use which template. See [Choosing templates](choosing-templates.md).
3. **Add the field to your entry types.** Optional, but useful. Create a field of the **Social image** type and add it to your entry types' field layouts. It shows each entry's image, with a button to remake it, and lets an entry choose another template or an image of its own. See [In an entry](control-panel.md#in-an-entry).
4. **Make the images.** New saves make them as they go. For the entries you have already, go to **Social Images → Images** and choose **Generate missing**, or run:

    ```bash
    php craft social-images/generate
    ```

5. **Put them in your pages.** With SEOmatic, there's nothing to do: see [SEOmatic](seo.md). Without it, add the meta tags to your layout's `<head>`:

    ```twig
    {{ craft.socialImages.meta() }}
    ```

To check, share a page's address in a private message to yourself, or paste it into a link preview tool. Social networks cache previews: a page shared before it had an image may need refreshing with the network's own tool (Facebook's Sharing Debugger, LinkedIn's Post Inspector).

## Updating

```bash
composer update 1994/social-images-craft
php craft up
```

See the [changelog](../CHANGELOG.md). An update that changes how templates are drawn says so, and images are remade the next time each entry is saved or generated.

## Uninstalling

Remove the Social image field from your field layouts, then:

```bash
php craft plugin/uninstall social-images
composer remove 1994/social-images-craft
```

Your templates stay in Project Config, your fonts and pictures in `social-images`, and the images in their folder until you delete them.
