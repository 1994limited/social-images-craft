<?php

namespace nineteenninetyfour\socialimages\models;

use craft\base\Model;
use craft\helpers\App;

/**
 * The plugin's settings.
 *
 * Those on the settings page are saved to Project Config. The rest are set
 * in config/social-images.php only (see CONFIG_ONLY): they can hold
 * secrets, or differ between environments, so they're never saved.
 */
class Settings extends Model
{
    /** Set in config/social-images.php only, never saved to Project Config. */
    public const CONFIG_ONLY = ['enabled', 'renderer', 'views', 'libraryPath', 'rateLimitWait', 'rateLimitAttempts', 'importMaxKilobytes', 'importTimeout'];

    /**
     * Which template entries use: the first rule that matches an entry's
     * section and site wins.
     *
     * @var array<int, array{sections: array<int, string>, sites: array<int, string>, template: string, layouts: array<int, string>}>
     */
    public array $rules = [];

    public bool $generateOnSave = true;

    /** An asset shared for pages with no image yet. */
    public ?int $fallbackImageId = null;

    /** When the social image is used for SEOmatic's tags: replace (unless the entry has its own), always, off. */
    public string $seomatic = 'replace';

    /** The filesystem images are saved to: a handle, or empty for web/social-images. */
    public string $imagesFs = '';

    /** The folder inside it. */
    public string $imagesPath = 'social-images';

    // Config file only.

    /** When false, nothing is generated and pages use the fallback image. */
    public bool $enabled = true;

    /** Where fonts and images added in the designer are kept, to commit with the site. */
    public string $libraryPath = '@root/social-images';

    /**
     * What renders the images, and each renderer's options.
     *
     * @var array{driver?: string, drivers?: array<string, array>}
     */
    public array $renderer = [];

    /**
     * Twig templates to use as social image templates, by handle.
     *
     * @var array<string, array{title?: string, template: string, layouts?: array, version?: string}>
     */
    public array $views = [];

    public int $rateLimitWait = 15;

    public int $rateLimitAttempts = 4;

    public int $importMaxKilobytes = 10240;

    public int $importTimeout = 15;

    public function init(): void
    {
        parent::init();

        $this->renderer = array_replace_recursive([
            'driver' => App::env('SOCIAL_IMAGES_RENDERER') ?: 'browsershot',
            'drivers' => [
                'browsershot' => [
                    'node_binary' => App::env('SOCIAL_IMAGES_NODE_BINARY') ?: null,
                    'npm_binary' => App::env('SOCIAL_IMAGES_NPM_BINARY') ?: null,
                    'chrome_path' => App::env('SOCIAL_IMAGES_CHROME_PATH') ?: null,
                    'node_modules_path' => App::env('SOCIAL_IMAGES_NODE_MODULES_PATH') ?: null,
                    'no_sandbox' => (bool) App::env('SOCIAL_IMAGES_NO_SANDBOX'),
                ],
                'cloudflare' => [
                    'api_token' => App::env('CLOUDFLARE_API_TOKEN') ?: null,
                    'account_id' => App::env('CLOUDFLARE_ACCOUNT_ID') ?: null,
                ],
            ],
        ], $this->renderer);
    }

    /**
     * What's saved to Project Config: the settings page's values only.
     */
    public function fields(): array
    {
        return array_diff_key(parent::fields(), array_flip(self::CONFIG_ONLY));
    }

    protected function defineRules(): array
    {
        return [
            [['seomatic'], 'in', 'range' => ['replace', 'always', 'off']],
            [['imagesFs'], 'match', 'pattern' => '/^[a-zA-Z0-9_\-$]*$/'],
            [['imagesPath'], 'match', 'pattern' => '/^[a-zA-Z0-9_\-\/]*$/'],
            [['generateOnSave'], 'boolean'],
            [['fallbackImageId'], 'integer'],
        ];
    }

    /**
     * The rules, tidied: unknown keys dropped, lists made lists.
     *
     * @return array<int, array{sections: array<int, string>, sites: array<int, string>, template: string, layouts: array<int, string>}>
     */
    public function templateRules(): array
    {
        $rules = [];

        foreach ($this->rules as $rule) {
            if (! is_array($rule) || empty($rule['template'])) {
                continue;
            }

            $rules[] = [
                'sections' => array_values(array_filter((array) ($rule['sections'] ?? []), 'is_string')),
                'sites' => array_values(array_filter((array) ($rule['sites'] ?? []), 'is_string')),
                'template' => (string) $rule['template'],
                'layouts' => array_values(array_filter((array) ($rule['layouts'] ?? []), 'is_string')),
            ];
        }

        return $rules;
    }
}
