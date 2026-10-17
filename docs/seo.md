# SEOmatic

With [SEOmatic](https://plugins.craftcms.com/seomatic) installed, Social Images gives SEOmatic the entry's image as its SEO image, with its width, height and alt text. SEOmatic's `og:image` and `twitter:image` follow the SEO image unless you've set them apart, so there's nothing to add to your templates: SEOmatic already outputs them.

It works through SEOmatic's dynamic meta event, as SEOmatic builds a page's tags. SEOmatic caches those tags, so when an entry's new image is made, SEOmatic's cache for its section is cleared.

## When

Under **Social Images → Settings → SEOmatic**:

- **Unless the entry has its own SEO image** (the default): the social image is used only while the page's SEO image is the site-wide one from SEOmatic's global settings, or there's none. An SEO image set for the entry or its section in SEOmatic is kept.
- **Always**: the social image is used for every entry that has one, whatever its SEOmatic settings say.
- **Never**: SEOmatic is left alone. Use the [Twig](tags.md) wherever you like.

While an entry's new image is being made, its previous one is used. An entry that has never had one keeps SEOmatic's own image: the [fallback image](choosing-templates.md#the-fallback-image) never replaces SEOmatic's. An image chosen in the entry's **Use this image instead** does.

For pages that aren't entries (categories, routes of your own), SEOmatic works as it did.

Don't add `{{ craft.socialImages.meta() }}` as well: the page would have two sets of image tags.

## Other SEO plugins

With another SEO plugin, turn off its image tags (or leave its image fields empty) and add [`{{ craft.socialImages.meta() }}`](tags.md#the-meta-tags) to your layout's `<head>`. Using one we should work with directly? Tell us at [hello@1994.co.uk](mailto:hello@1994.co.uk).
