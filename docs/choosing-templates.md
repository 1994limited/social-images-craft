# Choosing templates

Which template an entry uses, in order:

1. **The entry's own choice**, in its **Social image** field (see [In an entry](control-panel.md#in-an-entry)): another template, or **None**.
2. **The first rule that matches**, under **Social Images → Settings**.
3. **None**: the entry gets no generated image, and pages use the [fallback image](#the-fallback-image).

Only entries with a URL get images: an entry in a section without URIs is never shared, so it has none. Drafts, revisions and nested entries (in Matrix fields, say) don't get images either.

Entries keep their ID across sites in Craft, but each site's entry gets its own image, made in that site's language with that site's values.

## Rules

Each rule has:

- **Sections**: the sections it's for. None ticked means every section.
- **Sites** (on multi-site installs): the sites it's for. None ticked means every site.
- **Template**: the template they use.
- **Also make**: extra sizes to make when an entry is saved, beside Open Graph. See [Sizes](#sizes).

Rules are checked from the top; the first one that matches an entry is used. So put particular rules first and a catch-all last:

| Sections | Template |
| --- | --- |
| Books | Book jacket |
| Journal | Editorial |
| *(none ticked)* | Bold gradient |

The settings are saved to Project Config, so they can only be changed where admin changes are allowed, and deploy with the rest of your config.

## Sizes

Every template has an **Open Graph** layout (1200 × 630), which is what `og:image` and `twitter:image` use and what's made on save. A template can also have other layouts: square, portrait, Pinterest, story, or any size. See [Sizes in the designer](designer.md#sizes).

The other sizes are made when an entry is saved only if its rule ticks them under **Also make**, which lists square, portrait, Pinterest and story. Otherwise one is made the first time a page asks for it, with `craft.socialImages.url(entry, 'square')` (see [Twig](tags.md)). Until it exists, that gives the fallback image.

## An entry's own image

The **Use this image instead** setting in an entry's Social image field takes an image asset, used in place of the generated one everywhere. 1200 × 630 is the size social networks expect.

## The fallback image

Under **Settings → Generating → Fallback image**, an image asset for pages that have no social image yet: while an entry's first image is being made (later ones replace the previous image only once they're ready), and for pages with no template (like your home page, if no rule covers it, or pages that aren't entries).

With SEOmatic, the fallback never replaces SEOmatic's own image. See [SEOmatic](seo.md).

## When images change

An image's file name is a fingerprint of everything in it: the template, the entry's text and pictures, the fonts. Social Images works out the fingerprint when an entry is saved or its image is asked for. If a file with that name already exists, the image is still right and nothing happens; if not, a new one is made and the old one deleted.

So:

- Changing a field the image shows, or replacing its picture, or moving the picture's focal point, makes a new image on save.
- Changing a field the image doesn't show makes nothing.
- Changing a template (in the designer, or by deploying a Project Config change) queues new images for every entry using it that already had one. Entries that never had one get theirs when a page asks for it, when an editor opens the entry, or when you choose **Generate missing** on the Images page (or run `social-images/generate`). Either way, nothing is deleted first: each entry keeps its old image, on its pages and in the control panel, until the new one is ready.
- Changing a rule in the settings queues nothing: entries move to their new template as they're saved or their images are asked for, or when you choose **Update out of date** on the Images page.
- Deleting an entry deletes its images, in every site.

Code templates are the exception: a Twig template can show anything about an entry, so any change to the entry (or the template's file) makes a new image. See [Code templates](code-templates.md#when-images-change).
