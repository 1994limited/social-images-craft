<?php

namespace nineteenninetyfour\socialimages\services;

use Craft;
use craft\events\ConfigEvent;
use craft\helpers\StringHelper;
use nineteenninetyfour\socialimages\base\Template;
use NineteenNinetyFour\SocialImages\Core\Core;
use NineteenNinetyFour\SocialImages\Core\Support\Lang;
use NineteenNinetyFour\SocialImages\Core\Templates\InvalidTemplate;
use NineteenNinetyFour\SocialImages\Core\Templates\Schema;
use NineteenNinetyFour\SocialImages\Core\Templates\TemplateStore;
use nineteenninetyfour\socialimages\events\TemplateEvent;
use nineteenninetyfour\socialimages\models\CodeTemplate;
use nineteenninetyfour\socialimages\models\DesignerTemplate;
use nineteenninetyfour\socialimages\Plugin;
use yii\base\Component;

/**
 * Every template: the designer's, kept in Project Config so they deploy
 * with the site like sections and fields, and the code templates listed in
 * config/social-images.php.
 *
 * Each designer template is one key, socialImages.templates.{uid}, holding
 * its handle, title and layouts (as JSON, so Project Config stores them
 * exactly as Schema made them).
 */
class Templates extends Component implements TemplateStore
{
    public const CONFIG_KEY = 'socialImages.templates';

    /** After a designer template is saved here or by a Project Config change. */
    public const EVENT_AFTER_SAVE_TEMPLATE = 'afterSaveTemplate';

    /** @var array<string, Template>|null */
    protected ?array $all = null;

    /**
     * @return array<string, Template> by handle, designer templates first, each by title
     */
    public function all(): array
    {
        if ($this->all !== null) {
            return $this->all;
        }

        $designer = [];

        foreach ((array) Craft::$app->getProjectConfig()->get(self::CONFIG_KEY) as $uid => $item) {
            $template = $this->fromConfig((string) $uid, (array) $item);

            if ($template) {
                $designer[$template->handle()] = $template;
            }
        }

        uasort($designer, fn ($a, $b) => strcasecmp($a->title(), $b->title()));

        $code = [];

        foreach (Plugin::getInstance()->getSettings()->views as $handle => $config) {
            $handle = Schema::handle($handle);

            if ($handle && is_array($config) && ! isset($designer[$handle])) {
                $code[$handle] = new CodeTemplate($handle, $config);
            }
        }

        return $this->all = $designer + $code;
    }

    public function find(?string $handle): ?Template
    {
        return $handle === null ? null : ($this->all()[$handle] ?? null);
    }

    public function designer(?string $handle): ?DesignerTemplate
    {
        $template = $this->find($handle);

        return $template instanceof DesignerTemplate ? $template : null;
    }

    public function exists(string $handle): bool
    {
        return $this->find($handle) !== null;
    }

    /**
     * Save a designer template, new or changed.
     *
     * @throws InvalidTemplate
     */
    public function save(string $handle, array $data): DesignerTemplate
    {
        $handle = Schema::handle($handle) ?? throw new InvalidTemplate(Lang::get('Handles can use lower-case letters, numbers and underscores.'));

        if ($this->find($handle) instanceof CodeTemplate) {
            throw new InvalidTemplate(Lang::get('A code template already uses that handle.'));
        }

        $data = Schema::normalize($data);
        $uid = $this->designer($handle)?->uid ?? StringHelper::UUID();

        Craft::$app->getProjectConfig()->set(self::CONFIG_KEY.'.'.$uid, [
            'handle' => $handle,
            'title' => $data['title'],
            'data' => json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ], "Save social image template “{$handle}”");

        $this->all = null;

        return $this->designer($handle);
    }

    public function delete(string $handle): void
    {
        if ($template = $this->designer($handle)) {
            Craft::$app->getProjectConfig()->remove(self::CONFIG_KEY.'.'.$template->uid, "Delete social image template “{$handle}”");
            $this->all = null;
        }
    }

    public function uniqueHandle(string $title): string
    {
        $base = Schema::handle(Schema::slug($title)) ?? 'template';
        $base = substr($base, 0, 50);
        $handle = $base;

        for ($i = 2; $this->exists($handle); $i++) {
            $handle = $base.'_'.$i;
        }

        return $handle;
    }

    /**
     * The starters that come with Social Images, by handle.
     *
     * @return array<string, array>
     */
    public function starters(): array
    {
        $starters = [];

        foreach (glob(Core::path('resources/templates/*.json')) ?: [] as $file) {
            $data = json_decode((string) file_get_contents($file), true);

            if (is_array($data)) {
                $starters[basename($file, '.json')] = Schema::normalize($data);
            }
        }

        uasort($starters, fn ($a, $b) => strcasecmp($a['title'], $b['title']));

        return $starters;
    }

    /**
     * Project Config changed a template (a save here, or a deploy): forget
     * what's cached and tell listeners, which remake its images.
     */
    public function handleChanged(ConfigEvent $event): void
    {
        $this->all = null;

        $template = $this->fromConfig((string) $event->tokenMatches[0], (array) $event->newValue);

        if ($template && $this->hasEventHandlers(self::EVENT_AFTER_SAVE_TEMPLATE)) {
            $this->trigger(self::EVENT_AFTER_SAVE_TEMPLATE, new TemplateEvent(['template' => $template]));
        }
    }

    public function handleRemoved(ConfigEvent $event): void
    {
        $this->all = null;
    }

    protected function fromConfig(string $uid, array $item): ?DesignerTemplate
    {
        $handle = Schema::handle($item['handle'] ?? null);
        $data = is_string($item['data'] ?? null) ? json_decode($item['data'], true) : null;

        if (! $handle || ! is_array($data)) {
            return null;
        }

        try {
            return new DesignerTemplate($handle, Schema::normalize($data), $uid);
        } catch (InvalidTemplate) {
            return null;
        }
    }
}
