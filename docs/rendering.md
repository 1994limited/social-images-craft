# Rendering

An image is a screenshot of a web page: Social Images builds the page for an entry (its template, with the entry's text, pictures and fonts inside it) and has a browser take a picture of it. That browser is either of two:

| | Browsershot | Cloudflare Browser Rendering |
| --- | --- | --- |
| What runs | Chrome on your server, through Node.js | Chrome on Cloudflare's servers |
| Server needs | Node.js, the `puppeteer` package, Chrome or Chromium | Nothing |
| Cost | Free | Free plan: 10 minutes of rendering a day (about 200–300 images); paid plans for more |
| Good for | Your own computer, and servers where you can install Chrome | Hosting where you can't install Chrome |

Each image takes one to three seconds either way.

The page is sent to the browser as HTML, with everything inside it, so the browser never fetches anything from your site. That's why Cloudflare can render images for a site it can't reach, like a local `.test` site.

## Renderers

What drives the browser is a *renderer*, chosen with `SOCIAL_IMAGES_RENDERER` in `.env`:

| Renderer | Set up with |
| --- | --- |
| `browsershot` (the default) | `SOCIAL_IMAGES_NODE_BINARY`, `SOCIAL_IMAGES_NPM_BINARY`, `SOCIAL_IMAGES_CHROME_PATH`, `SOCIAL_IMAGES_NODE_MODULES_PATH` and `SOCIAL_IMAGES_NO_SANDBOX`. |
| `cloudflare` | `CLOUDFLARE_API_TOKEN` and `CLOUDFLARE_ACCOUNT_ID`. Tells a per-minute limit from a used-up day, and honours Cloudflare's `Retry-After`. |
| Your own | See [Adding a renderer](php-api.md#adding-a-renderer). |

Each renderer's options are under `renderer.drivers` in `config/social-images.php` (see [Configuration](configuration.md#rendering)). Keep tokens in `.env` only: never in config files or the repository. The renderer settings are never saved to Project Config.

**Privacy.** A remote renderer (Cloudflare, or a service you add) is sent each image's page: the entry's title and the other text and pictures its template shows. Only fields the template uses are included, but if those aren't public yet (a disabled entry, say), choose a renderer that runs on your server.

### Checking it works

```bash
php craft social-images/doctor
```

renders a small test image and says whether it worked, and if not, what to change. `--driver=cloudflare` checks another renderer before you switch to it. In the control panel, **Social Images → Settings → Rendering → Test** does the same for the renderer in use.

## Browsershot

Install Browsershot and Puppeteer in your project:

```bash
composer require spatie/browsershot
npm install puppeteer
```

Puppeteer downloads its own Chrome. To use a Chrome you have already, install it with `PUPPETEER_SKIP_DOWNLOAD=1 npm install puppeteer` and give its path below.

Then in `.env`:

```env
SOCIAL_IMAGES_NODE_BINARY=/usr/local/bin/node
SOCIAL_IMAGES_NPM_BINARY=/usr/local/bin/npm
SOCIAL_IMAGES_CHROME_PATH="/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"
```

Set the paths even if `node` works in your terminal: PHP run by a web server (Herd, Valet, DDEV, PHP-FPM) doesn't see your shell's `PATH`. Find them with `which node` and `which npm`. If Puppeteer isn't in your project's `node_modules`, set `SOCIAL_IMAGES_NODE_MODULES_PATH` to the folder that has it. On Linux servers you may also need `SOCIAL_IMAGES_NO_SANDBOX=true`.

## Cloudflare Browser Rendering

1. In the [Cloudflare dashboard](https://dash.cloudflare.com), note your **Account ID** (on the account's home page).
2. Under **My Profile → API Tokens**, create a token with the **Account → Browser Rendering → Edit** permission for that account.
3. In `.env`:

    ```env
    SOCIAL_IMAGES_RENDERER=cloudflare
    CLOUDFLARE_API_TOKEN=your-token
    CLOUDFLARE_ACCOUNT_ID=your-account-id
    ```

Keep the token in `.env` only: never in config files or the repository.

### Limits

Cloudflare's free plan allows a limited amount of rendering a day and a few browsers at once (see [Cloudflare's limits](https://developers.cloudflare.com/browser-rendering/limits/) for today's numbers). When a render is turned away for going too fast:

- **A queued render** goes back on the queue, to try again after the wait the service asks for, or after 15 seconds, then 30, then 45 (`rateLimitWait` seconds, longer each time). After four refusals in a row (`rateLimitAttempts`) the job fails. When the day's allowance has run out (the `cloudflare` renderer can tell), it waits until it resets, at midnight UTC, and pages don't try again in the meantime.
- **`social-images/generate`** waits and tries again in the same way, and stops after four refusals in a row, saying the day's allowance has probably run out (or straight away, when the renderer says so). Run it again later: it skips the images already made. See [Commands](commands.md).
- **Generate missing / Regenerate all** on the Images page does the same, with a countdown on the page.

A site with a few hundred entries fits in a day on the free plan. For more, generate in batches (`--section`), or use a paid plan.

## The queue

Images are rendered in Craft's queue wherever they can be, so nobody waits for one.

| What starts a render | How |
| --- | --- |
| Saving an entry | Queued, for each site it's saved in |
| A page asking for an image that's missing or out of date | Queued |
| Opening an entry in the control panel | Queued |
| Saving a template, or a Project Config change applying one (on a deploy, say) | One job is queued, which queues a render for each entry using the template that already had an image |
| **Regenerate** in an entry, and **Generate** / **Regenerate all** on the Images page | Rendered there and then, one image per request, so you see each land |
| `social-images/generate` | Rendered by the command, one at a time (`--queue` queues them instead) |
| **Regenerate social images** on the entries index | Queued |

By default Craft runs queued jobs while someone is using the control panel. On a live site, run a queue worker (`php craft queue/listen`, kept running by your server) so images are made when nobody is signed in. **Utilities → Queue Manager** shows the jobs waiting and any that failed.

Renders are unique per entry, site, size and content: saving an entry five times in a minute without changing what its image shows makes nothing; changing it twice makes two renders, and the second replaces the first. A render queued for a version of an entry that has since changed stands down, since the change queued its own.

Nothing is deleted before its replacement exists: pages and the control panel keep showing an entry's previous image until the new one is ready.

## Turning it off

In `config/social-images.php`:

```php
return [
    'enabled' => false,
];
```

Nothing is made on save, on page view, after a template changes or by the command; pages use the [fallback image](choosing-templates.md#the-fallback-image). Images already made are kept and still used. Handy on staging sites that shouldn't spend your Cloudflare allowance; see [Configuration](configuration.md#turning-it-off) for turning it off in one environment only.
