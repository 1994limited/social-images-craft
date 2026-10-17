# Template format

A designer template is JSON. You don't need to know it to use the designer; it's here for anyone writing or generating templates, or curious what an import contains.

When a template is saved or imported, every value is checked, kept within its limits, and anything not listed here is dropped (see [What a template can and can't do](importing.md#what-a-template-can-and-cant-do)).

```json
{
    "schema": "social-images/1",
    "title": "Editorial",
    "description": "A large serif headline on paper.",
    "layouts": {
        "og": {
            "width": 1200,
            "height": 630,
            "background": { "type": "solid", "color": "#f6f1e7" },
            "layers": [
                {
                    "id": "title",
                    "type": "text",
                    "name": "Title",
                    "text": "{title}",
                    "x": 80, "y": 160, "width": 1040, "height": 300,
                    "font": { "family": "Fraunces", "weight": 600, "style": "normal" },
                    "size": 84, "min_size": 36, "max_lines": 3,
                    "color": "#1f1a14"
                }
            ]
        }
    },
    "fonts": [],
    "uploads": {}
}
```

## The template

| Key | |
| --- | --- |
| `schema` | Always `social-images/1`. A file for another version is refused. |
| `title` | Up to 100 characters. |
| `description` | Optional, up to 300. |
| `layouts` | The sizes, by handle (lower case, letters, numbers and `_`). `og` is required and always first; up to 8 in all. `square`, `portrait`, `pinterest` and `story` have preset sizes. |
| `fonts` | Exports only: the fonts that didn't come with Social Images, inlined. See [Fonts](#fonts). |
| `uploads` | Exports only: the uploaded pictures, inlined, by name. See [Uploads](#uploads). |

## A layout

| Key | |
| --- | --- |
| `width`, `height` | 200 to 2400 pixels. |
| `background` | A [fill](#fills). |
| `layers` | Up to 60, drawn in order: the last is in front. |

## Every layer

| Key | | Default |
| --- | --- | --- |
| `id` | Letters, numbers, `-` and `_`, up to 40; unique in its layout. | made up |
| `type` | `text`, `image` or `shape`. Others are dropped. | |
| `name` | Shown in the layer list. | |
| `x`, `y` | The top-left corner, before rotation. | 0 |
| `width`, `height` | | 200, 100 |
| `rotate` | Degrees, about the centre. | 0 |
| `opacity` | 0 to 1. | 1 |
| `hidden` | Left out of the image. | false |
| `locked` | Can't be selected on the canvas. | false |
| `when` | Fields that must have a value for the layer to show, as `subtitle ?? excerpt`. | null |

## Text

| Key | | Default |
| --- | --- | --- |
| `text` | Up to 500 characters, with `{fields}`. See [Text](designer.md#text). | |
| `font` | `{ "family": "Inter", "weight": 400, "style": "normal" }`. Weight 100 to 900; style `normal` or `italic`. | Inter 400 |
| `size`, `min_size` | Largest and smallest size in pixels, 6 to 400. | 64, `size` |
| `max_lines` | 0 for no limit. | 0 |
| `line_height` | A multiple of the size, 0.6 to 3. | 1.15 |
| `letter_spacing` | In ems, -0.2 to 1. | 0 |
| `color` | A [colour](#colours). | `#111111` |
| `align` | `left`, `center` or `right`. | left |
| `valign` | `top`, `middle` or `bottom`. | top |
| `transform` | `none`, `uppercase`, `lowercase` or `capitalize`. | none |
| `balance` | Even out the lines. | true |
| `hug` | The background fits the text (a label). | false |
| `background` | A [fill](#fills), or null. | null |
| `padding` | `[top, right, bottom, left]` in pixels, or one number. | 0 |
| `radius` | Corner radius in pixels. | 0 |
| `border` | `{ "width": 1, "color": "#000" }`, or null. | null |
| `shadow` | `{ "x": 0, "y": 10, "blur": 30, "color": "#0003" }`, or null. | null |

## Images

| Key | | Default |
| --- | --- | --- |
| `source` | Up to 6, tried in order: `field:handle`, `field:relation.handle`, `upload:{sha1}.{ext}`, `asset:{id}` or `asset:{uid}`. See below. | `[]` |
| `fit` | `cover` (crop) or `contain`. | cover |
| `grayscale` | | false |
| `background`, `radius`, `border`, `shadow` | As for text. | |

In Craft:

- `field:featuredImage` is the first asset in an assets field. A snake_case handle finds a camelCase field (`field:featured_image` finds `featuredImage`).
- `field:reviewer.headshot` is an assets field of the first element a relation field points to. `field:author.photo` is the entry author's user photo.
- `asset:42` or `asset:{uid}` is one particular asset, by its ID or UID. Statamic's `asset:container::path` is accepted, but finds nothing in Craft.

## Shapes

| Key | | Default |
| --- | --- | --- |
| `shape` | `rect` or `ellipse`. | rect |
| `fill` | A [fill](#fills). | null |
| `blur` | Pixels. | 0 |
| `radius`, `border`, `shadow` | As for text. | |

## Fills

```json
{ "type": "solid", "color": "#4f46e5" }
{ "type": "linear", "angle": 135, "stops": [{ "color": "#4f46e5", "position": 0 }, { "color": "#ec4899", "position": 100 }] }
{ "type": "radial", "x": 50, "y": 50, "stops": [{ "color": "#ffffff55", "position": 0 }, { "color": "#ffffff00", "position": 70 }] }
```

Gradients take 2 to 8 stops, positions 0 to 100. A plain colour string is taken as a solid fill.

## Colours

Hex only: `#rgb`, `#rgba`, `#rrggbb` or `#rrggbbaa` (the last two digits are opacity), or `transparent`. Anything else is dropped.

## Fonts

A font family is named in text layers. If it's one of the [included fonts](designer.md#fonts), that's all; otherwise an export carries it in `fonts`:

```json
{ "family": "House Sans", "style": "normal", "weight": [400, 700], "unicode_range": null, "source": "upload", "data": "data:font/woff2;base64,…" }
```

On import, a font the site doesn't have is added to its fonts. Font names may use letters, numbers, spaces, dots, dashes, underscores and `&`.

## Uploads

Pictures uploaded to a template are named by the SHA-1 of their contents and referred to as `upload:{name}`. An export carries them in `uploads`, as data URIs; site assets (`asset:…`) are turned into uploads on export, since another site won't have them.

The format is the same in Social Images for Craft and for Statamic.
