# Troubleshooting

## An entry's image says "Not generated yet" or "Waiting to start…"

- **Is it saved?** Images are made from the saved entry, after the save. Drafts don't get images.
- **Does the entry have a URL?** Only entries in sections with URIs get images.
- **Does rendering work?** `php craft social-images/doctor` renders a test image and says what to change if it can't. See [Checking it works](rendering.md#checking-it-works).
- **Is the queue running?** Craft runs queued jobs while the control panel is in use; on a live site, run a worker (`php craft queue/listen`). **Utilities → Queue Manager** shows what's waiting and what failed. See [The queue](rendering.md#the-queue).
- **Did the render fail?** Choose **Regenerate** in the entry's Social image field: it renders there and then and says if it couldn't. The error itself is in `storage/logs`, and a failed job's in the Queue Manager. After a failure, page views don't try that image again for five minutes, so a broken template or a used-up allowance doesn't cost a render on every visit; **Regenerate** always tries.
- **Is it turned off?** `'enabled' => false` in `config/social-images.php`, or **Generate when an entry is saved** off in the settings.

## "The image couldn't be rendered"

**Render** in the designer, `social-images/doctor` and the settings' **Test** show the renderer's own message; the command and **Test** also say what to change.

| Message | |
| --- | --- |
| `The Browsershot renderer needs the spatie/browsershot package` | `composer require spatie/browsershot` and `npm install puppeteer`, or switch to Cloudflare. See [Rendering](rendering.md). |
| `Chrome couldn't be started` … `Could not find Chrome` / `Failed to launch the browser process` | Set `SOCIAL_IMAGES_CHROME_PATH`, or install Puppeteer's Chrome (`npx puppeteer browsers install chrome`). On Linux, try `SOCIAL_IMAGES_NO_SANDBOX=true`. |
| `Chrome couldn't be started` … `node: command not found`, `npm: not found` | Set `SOCIAL_IMAGES_NODE_BINARY` and `SOCIAL_IMAGES_NPM_BINARY` to full paths (`which node`). PHP under a web server doesn't see your shell's `PATH`. |
| `Chrome couldn't be started` … `Cannot find module 'puppeteer'` | `npm install puppeteer` in your project, or set `SOCIAL_IMAGES_NODE_MODULES_PATH`. |
| `Cloudflare needs an API token and an account ID` | Set `CLOUDFLARE_API_TOKEN` and `CLOUDFLARE_ACCOUNT_ID`. |
| `Cloudflare refused the API token` | The token needs **Account → Browser Rendering → Edit**, for the same account as the ID. |
| `Cloudflare is getting too many renders at once`, `Cloudflare's browser time for today has run out` | Cloudflare's limits. Wait, or see [Limits](rendering.md#limits). |
| A timeout waiting for the page | The page never finished: in a code template, a script error, or a picture or font from the network that never arrived. See [Loading nothing](code-templates.md#loading-nothing). |

## The image is missing text or a picture

Text with no value and image layers with no picture are left out, on purpose. Choose the entry under **Preview with** in the designer: layers that would be left out are faded. Check the field's handle in **Insert a field**, and that a picture field holds an image asset.

## Fonts look wrong

- In the designer, a font only shows once the page has loaded it; give it a moment after choosing one.
- A weight a font doesn't have uses its nearest. The weight menu lists the ones it has.
- An uploaded font needs a file per weight and style.
- In a code template, fonts must come from `craft.socialImages.font()`, not a link to Google Fonts or your site's CSS.
- On another environment, check the `social-images` folder at your project's root was deployed: fonts added in the designer are kept there, not in Project Config.

## Social networks show an old image, or none

Networks keep a page's preview for days. Ask them to look again: Facebook's [Sharing Debugger](https://developers.facebook.com/tools/debug/), LinkedIn's [Post Inspector](https://www.linkedin.com/post-inspector/). Check the page's source has `og:image`, and that its address opens.

If the address starts with `http://localhost` or your local domain, check the site's base URL in **Settings → Sites** (and the `@web` alias, if you set it) is your live address.

## The image's address gives a 404

With no filesystem chosen, images are saved in `web/social-images`: check the web root is writable. With a filesystem, check its base URL. See [Where images are saved](configuration.md#where-images-are-saved).

## Changed a template, but pages show the old image

Pages keep the image they have until a new one is made. After a template change, the queue remakes the images of entries that had one: check the queue is running. Otherwise they're made when each entry is next saved, when a page asks for it, or when you choose **Update out of date** on the Images page. See [When images change](choosing-templates.md#when-images-change).

## "Templates can only be changed where admin changes are allowed"

Templates and settings are kept in Project Config. Change them where `allowAdminChanges` is on (usually your own computer), then deploy.

## Importing fails

The message says why. "Templates can only be imported from public addresses": the address is on your own network or `localhost`. Download the file and import it from **File** instead.
