# Commands

## Generating images

```bash
php craft social-images/generate
```

Makes every image that doesn't exist yet, one at a time, and lists each as it lands, by section, URI, site and size. Images that are already current are skipped, so it's safe to run again (for instance after a deploy, or when a run was stopped).

| Option | |
| --- | --- |
| `--section=books,journal` | Only entries in these sections, by handle. |
| `--entry=12,15` | Only these entries, by ID. |
| `--site=en` | Only entries in this site, by handle. Without it, every site. |
| `--layouts=square` | Only these sizes, comma-separated. Without it, each entry's sizes: Open Graph and any its rule ticks. |
| `--force` | Remake images that are already current. |
| `--queue` | Push each render to the queue instead of rendering here. |

When the screenshot service turns a render away for going too fast, the command waits (as long as the service asks, or `rateLimitWait` seconds, longer each time) and tries again. After `rateLimitAttempts` refusals in a row it stops and says so: usually a daily allowance has run out. When the renderer says the allowance has run out, it stops straight away and says when it resets. See [Limits](rendering.md#limits) and [Configuration](configuration.md#rate-limits).

It exits with an error if any image failed, if no entries with a template match, or if Social Images is [turned off](rendering.md#turning-it-off), so it can run in a deploy script.

## Checking rendering

```bash
php craft social-images/doctor
```

Renders a small test image with the renderer in use and says whether it worked, and if not, what to change. It also says whether the renderer runs on your server or sends pages to another service, and lists the renderers available.

| Option | |
| --- | --- |
| `--driver=cloudflare` | Check this renderer instead, before switching to it. |

It exits with an error if the render failed, so it can run after a deploy.
