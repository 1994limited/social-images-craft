<?php

namespace nineteenninetyfour\socialimages\console\controllers;

use craft\console\Controller;
use craft\elements\Entry;
use craft\helpers\Console;
use nineteenninetyfour\socialimages\images\Card;
use nineteenninetyfour\socialimages\jobs\GenerateImage;
use nineteenninetyfour\socialimages\Plugin;
use nineteenninetyfour\socialimages\support\RateLimits;
use Throwable;
use yii\console\ExitCode;

/**
 * Makes the images entries don't have yet (or all of them, with --force),
 * one at a time in this process so each is reported as it lands. When the
 * screenshot service turns a render away for going too fast, it waits and
 * tries again; after several refusals in a row it stops, since that usually
 * means a daily allowance has run out. Run it again later to carry on: the
 * images already made are skipped.
 */
class GenerateController extends Controller
{
    public $defaultAction = 'index';

    /** @var string|null Only entries in these sections (handles, comma-separated) */
    public ?string $section = null;

    /** @var string|null Only these entry IDs (comma-separated) */
    public ?string $entry = null;

    /** @var string|null Only entries in this site (handle) */
    public ?string $site = null;

    /** @var string|null Only these layouts (comma-separated; default: each entry's layouts) */
    public ?string $layouts = null;

    /** @var bool Re-render images that already exist */
    public bool $force = false;

    /** @var bool Push each render to the queue instead of rendering here */
    public bool $queue = false;

    public function options($actionID): array
    {
        return [...parent::options($actionID), 'section', 'entry', 'site', 'layouts', 'force', 'queue'];
    }

    /**
     * Generates social images for existing entries.
     */
    public function actionIndex(): int
    {
        $plugin = Plugin::getInstance();

        if (! $plugin->getSettings()->enabled) {
            $this->stderr("Social images are disabled (enabled => false in config/social-images.php).\n", Console::FG_YELLOW);

            return ExitCode::UNSPECIFIED_ERROR;
        }

        $cards = $this->cards();

        if (! $cards) {
            $this->stderr("No entries with a template match.\n", Console::FG_YELLOW);

            return ExitCode::UNSPECIFIED_ERROR;
        }

        $failed = 0;

        foreach ($cards as $card) {
            $label = $card->entry->getSection()->handle.' '.($card->entry->uri ?? $card->entry->id).' '.$card->entry->getSite()->handle.' '.$card->layout;

            if (! $this->force && $card->exists()) {
                $this->stdout("  {$label} ".$this->ansiFormat('up to date', Console::FG_GREY)."\n");

                continue;
            }

            if ($this->queue) {
                GenerateImage::queue($card, $this->force);
                $this->stdout("  {$label} ".$this->ansiFormat('queued', Console::FG_BLUE)."\n");

                continue;
            }

            try {
                $path = $this->renderCard($card);
                $this->stdout("  {$label} ".$this->ansiFormat($path, Console::FG_GREEN)."\n");
            } catch (RateLimitReached $exception) {
                $this->stdout("  {$label} ".$this->ansiFormat('stopped', Console::FG_RED)."\n");
                $at = RateLimits::resetsAt($exception);
                $this->stderr(RateLimits::exhausted($exception)
                    ? 'The screenshot service’s allowance has run out'.($at ? ' until '.$at->format('j M H:i T') : '').". Run this again then to carry on.\n"
                    : "The screenshot service keeps refusing renders: its limit has probably been reached (Cloudflare’s free plan allows 10 minutes of rendering a day). Run this again later to carry on.\n", Console::FG_RED);

                return ExitCode::UNSPECIFIED_ERROR;
            } catch (Throwable $exception) {
                $failed++;
                $this->stdout("  {$label} ".$this->ansiFormat('failed', Console::FG_RED)."\n");
                $this->stderr('  '.$exception->getMessage()."\n", Console::FG_RED);

                if ($fix = RateLimits::fix($exception)) {
                    $this->stderr("  ⇂ {$fix}\n");
                }
            }
        }

        return $failed === 0 ? ExitCode::OK : ExitCode::UNSPECIFIED_ERROR;
    }

    protected function renderCard(Card $card, int $attempt = 1): string
    {
        try {
            return $card->render();
        } catch (Throwable $exception) {
            if (! RateLimits::caused($exception)) {
                throw $exception;
            }

            $settings = Plugin::getInstance()->getSettings();

            // No use waiting when the day's allowance is gone.
            if (RateLimits::exhausted($exception) || $attempt >= max(1, $settings->rateLimitAttempts)) {
                throw new RateLimitReached($exception->getMessage(), previous: $exception);
            }

            $wait = RateLimits::retryAfter($exception) ?? $settings->rateLimitWait * $attempt;
            $this->stdout("  Rate limited, trying again in {$wait}s…\n", Console::FG_YELLOW);
            sleep($wait);

            return $this->renderCard($card, $attempt + 1);
        }
    }

    /**
     * @return array<int, Card>
     */
    protected function cards(): array
    {
        $plugin = Plugin::getInstance();
        $query = Entry::find()->status(null)->sectionId(':notempty:')->site($this->site ?: '*');

        if ($this->entry) {
            $query->id(array_map('intval', explode(',', $this->entry)));
        }

        if ($this->section) {
            $query->section(explode(',', $this->section));
        }

        $layouts = $this->layouts ? explode(',', $this->layouts) : null;
        $cards = [];

        foreach ($query->all() as $entry) {
            if (! $plugin->cards->supports($entry)) {
                continue;
            }

            foreach ($layouts ?? $plugin->cards->layoutsFor($entry) as $layout) {
                if ($card = $plugin->cards->for($entry, $layout)) {
                    $cards[] = $card;
                }
            }
        }

        return $cards;
    }
}
