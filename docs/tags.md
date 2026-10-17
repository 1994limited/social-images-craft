# Twig

With [SEOmatic](seo.md) you may not need any of these: it puts the image in its own meta tags. They're for sites without SEOmatic, and for showing or linking to an image yourself.

Each takes `entry` (an entry; the entry whose page this is by default) and, apart from `meta()`, `layout` (`'og'` by default; any size the template has, like `'square'`).

## The meta tags

In your layout's `<head>`:

```twig
{{ craft.socialImages.meta() }}
```

gives

```html
<meta property="og:image" content="https://example.com/social-images/12/1/og-1c2d3e4f5a6b.png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="The entry's title">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:image" content="https://example.com/social-images/12/1/og-1c2d3e4f5a6b.png">
```

on an entry's page. On a page that isn't an entry's, it gives the [fallback image](choosing-templates.md#the-fallback-image), and nothing when there's no fallback. The width and height are left out when they aren't known. Add your own `og:title`, `og:description` and so on beside them.

## The image's address

```twig
{{ craft.socialImages.url() }}
```

For its size and more:

```twig
{% set image = craft.socialImages.image(entry, 'square') %}
{% if image %}
    <img src="{{ image.url }}" width="{{ image.width }}" height="{{ image.height }}" alt="{{ image.alt }}">
{% endif %}
```

| Key | |
| --- | --- |
| `url` | The image's full address |
| `width`, `height` | Its size in pixels (`null` for a fallback or own image whose size isn't known) |
| `alt` | The entry's title. For the entry's own image, its alt text (or the entry's title); for the fallback, its alt text (or the site's name) |
| `source` | `generated`, `override` (the entry's own image) or `fallback` |

`image()` and `url()` give `null` when there's no image and no fallback.

For another entry, pass it (the entry, not its ID): `craft.socialImages.url(item)` in a loop over entries.

## Which image

The entry's own image if it has one, otherwise its generated image, otherwise the fallback. When an entry's image needs making again (it was just changed), pages keep the image it replaces until the new one is ready; an entry that has never had one (or a size that's never been asked for) gets the fallback. Either way the new image is queued for the next visitor, and the page never waits. See [Choosing templates](choosing-templates.md).

## In code templates

`craft.socialImages.font()` and `craft.socialImages.embed()` are for [code templates](code-templates.md#loading-nothing).
