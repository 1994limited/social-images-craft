# Code templates

When the designer can't do what you need, write the template yourself: a Twig template that's screenshotted just as a designer template is. It gets the entry, as an entry's own template does, so anything Twig can do, it can.

## Making one

Put the template anywhere in your site's `templates` folder, say `templates/social-images/jacket.twig`, and list it under `views` in `config/social-images.php`:

```php
<?php

return [
    'views' => [
        'jacket' => [
            'title' => 'Jacket',
            'template' => 'social-images/jacket',
            'layouts' => [
                'og' => [1200, 630],
                'square' => [1080, 1080],
            ],
            'version' => '1',
        ],
    ],
];
```

The key (`jacket`) is its handle; it can't be the same as a designer template's. `layouts` lists its sizes, 200 to 2400 pixels each way; Open Graph (`og`, 1200 × 630) is added if you leave it out. It then appears on **Social Images → Templates** (marked **Code**) and in the settings' template choices, like any other.

Pages render in the entry's own site and language, so dates and translations come out as they do on the entry's page.

A template is a whole page, with nothing else around it:

```twig
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>
    {{ craft.socialImages.font('Fraunces', 600) }}
    {{ craft.socialImages.font('Inter', 500) }}
    body { margin: 0; width: {{ socialLayout.width }}px; height: {{ socialLayout.height }}px; background: #1f1a14; color: #f6f1e7; font-family: Inter, sans-serif; }
    .card { display: flex; gap: 64px; align-items: center; height: 100%; padding: 0 80px; box-sizing: border-box; }
    .cover { width: 320px; height: 480px; object-fit: cover; border-radius: 6px; }
    .text { flex: 1; height: 420px; display: flex; flex-direction: column; justify-content: center; }
    h1 { font-family: Fraunces, serif; font-weight: 600; line-height: 1.05; margin: 0 0 24px; }
</style>
</head>
<body>
    {% set cover = entry ? entry.coverImage.one() : null %}
    <div class="card">
        {% if cover %}
            <img class="cover" src="{{ craft.socialImages.embed(cover, 320, 480) }}" alt="">
        {% endif %}
        <div class="text">
            <h1 data-fit="88" data-fit-min="40" data-fit-lines="3">{{ entry.title ?? sample.title }}</h1>
            {% if entry %}
                <p>{{ entry.byline }} · {{ entry.postDate|date('j F Y') }}</p>
            {% endif %}
        </div>
    </div>
</body>
</html>
```

## What a template gets

- **`entry`**: the entry, as in its own template. It's `null` when there's no entry to show (a thumbnail of a template no entry uses yet), so check for it.
- **`socialLayout`**: the size being made, with `handle`, `width` and `height`.
- **`sample.title`**: placeholder text for when there's no entry.

## Loading nothing

The page should load nothing over the network: the browser taking the screenshot may not be able to reach your site (Cloudflare can't reach a local site), and anything it waits for slows every image. So fonts and pictures go inside the page:

- **`craft.socialImages.font(family, weight, style)`** outputs `@font-face` rules for a font from your font library (the included fonts, or ones added in the designer), with the font inside. Use it inside `<style>`. `weight` is 400 unless you give one, and `style` is `'normal'` or `'italic'`; the nearest the font has is used.
- **`craft.socialImages.embed(asset, width, height, fit)`** outputs an asset as a `data:` URI, resized with Craft's image library to the size you'll draw it at: cropped around its focal point, or `'contain'` as the fourth argument to fit inside. It takes an asset, or an asset query (and uses its first asset). It gives `null` for anything else.

Write your CSS in the page. Don't link stylesheets, Google Fonts or your site's built CSS.

## Content from other sources

The page is opened by Chrome on your server (with Browsershot), so anything in it can reach what your server can: its files and its private network. Treat what goes into a code template the way you would on any page, and be careful with content that comes from other people, such as entries editors write, front-end submissions or imported content:

- Keep Twig's escaping on. `{{ entry.title }}` is escaped; `|raw` and rich text fields aren't.
- Only output rich text or `|raw` from people you trust with the server. An `<iframe>` or `<img>` in it can point at a file on the server or an internal address, and what loads ends up in the public image.

Designer templates don't have this concern: their text is always shown as text and their pictures are inlined.

## Fitting text

Put `data-fit` on an element whose text should shrink to fit its parent: `data-fit="88"` is its largest size in pixels, `data-fit-min="40"` its smallest, and `data-fit-lines="3"` the most lines it may take. The parent needs a fixed size. This is the same fitting as the designer's text layers.

## When it's ready

Social Images adds its small script to the end of the page. Once the fonts and pictures have loaded and text has been fitted, it marks the page ready, and the screenshot is taken. You don't need to do anything for this; if your page does its own work in script, finish it before the page's `load` event.

## When images change

A code template can show anything about an entry, so its images are made again whenever the entry's field values, title, URI, site or post date change, or the template's file changes. See [When images change](choosing-templates.md#when-images-change).

What isn't noticed: changes to templates it includes or extends, and to entries, users or assets it reaches through fields (an author's name, a picture replaced in place). After changing those, change the template's `version` in the config, and change it whenever you want every image remade:

```php
'jacket' => [
    'title' => 'Jacket',
    'template' => 'social-images/jacket',
    'version' => '2',
],
```

Nothing is queued when a code template's file or `version` changes: its images are out of date, and are made again as pages ask for them, as editors open entries, or when you choose **Update out of date** on the Images page (or run `social-images/generate`).

## Trying it

**Social Images → Templates** shows each code template with a recent entry that uses it (or with no entry, if none does). To see it as a real screenshot, open an entry that uses it and choose **Regenerate** in its Social image field.
