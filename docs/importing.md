# Importing and exporting

A template made in the designer can be shared as one JSON file: send it to a colleague, keep it with a project, or publish it for anyone. The file holds the template's layouts and layers, plus everything another site wouldn't have: its uploaded pictures and site assets (inlined as images), and any fonts that didn't come with Social Images. Fonts that did are named, not inlined.

The format is the same in Social Images for Craft and for Statamic, so a template exported from one imports into the other.

## Exporting

In the designer, choose **Export**; or on **Social Images → Templates**, open a template's **⋯** menu and choose **Export**. The file is called `{handle}.social-image.json`.

## Importing

On **Social Images → Templates**, choose **Import**, then:

- **Paste**: paste the JSON.
- **File**: choose a `.json` file.
- **Address**: give the file's address (`https://…`). A GitHub file page (`https://github.com/…/blob/…`) is fetched from its raw address.

The template is added under its own title (with `_2` on its handle if you have one called that already), its pictures go into your uploads and its fonts into your fonts, and the designer opens.

Importing saves to Project Config, so it only works where admin changes are allowed. Import on your own computer, commit the `social-images` folder (where the pictures and fonts go) and deploy.

## What a template can and can't do

A template is layout and nothing else: positions, sizes, colours, gradients, font names, text with fields in braces, and where pictures come from. When it's imported, every value is checked and anything else in the file is thrown away. There's no HTML, CSS or script in a template, so importing one from anywhere is safe: it can't change your site, read your data or run code.

The things a template brings with it are checked too: pictures must really be PNG, JPEG, WebP, GIF, SVG or AVIF, fonts must really be WOFF2, WOFF, TTF or OTF, and each must be under 5 MB. Addresses are fetched only over https on the standard port, only from public servers (not your own network, cloud metadata services or addresses that wrap one), without following redirects, and up to 10 MB (see [Configuration](configuration.md#importing)).

Templates refer to entry fields by handle (`{subtitle}`, `field:featured_image`). A snake_case handle finds a camelCase field (`featured_image` finds `featuredImage`), so templates made for Statamic usually work as they are. If your fields are called something else, change them in the designer after importing: **Insert a field** lists yours.

## The gallery

[The gallery](https://github.com/1994limited/social-images-statamic/tree/main/gallery) has templates to import by address, each with a preview: the seventeen starters that come with the plugin, and more as we make them. They work in both Craft and Statamic.

## For code templates

[Code templates](code-templates.md) are files in your site, not JSON, and can't be imported in the control panel: a Twig template can run code, so it should only come from someone you'd let edit your site. Share them as files.

The file format is described in [Template format](template-format.md), if you'd like to write or generate templates yourself.
