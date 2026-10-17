# Changelog

## 1.0.1 - Unreleased

### Fixed
- SEOmatic's preview in the entry sidebar showed "No image set" for X and Facebook. It now shows the social image, as the page does.

### Documentation
- Screenshots of the designer, the Templates and Images pages and the Social image field.

## 1.0.0 - 2026-10-17

### Added
- A visual designer in the control panel: text, images, boxes and circles, bound to entries' fields, with text that fits its box, eight bundled fonts, Google Fonts and uploads, and any number of sizes per template.
- Templates kept in Project Config, so they deploy with the site. Seventeen starters, and importing and exporting templates as JSON, shared with Social Images for Statamic.
- Code templates: Twig templates with inlined fonts and pictures (`craft.socialImages.font()`, `craft.socialImages.embed()`).
- A Social image field: the entry's image with a Regenerate button, a choice of template, or an image to use instead.
- Images made in the queue when an entry is saved, per site, only when what they show has changed. The old image stays in use until the new one is ready.
- Social Images → Images: every entry's image, with Generate missing and Regenerate all. A Regenerate social images action on the entries index.
- `php craft social-images/generate` and `php craft social-images/doctor`.
- Rendering with Browsershot or Cloudflare Browser Rendering, or a renderer of your own. Rate limits and used-up allowances are waited out.
- SEOmatic: the social image as the page's SEO image. Without it, `{{ craft.socialImages.meta() }}`.
