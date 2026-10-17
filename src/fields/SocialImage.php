<?php

namespace nineteenninetyfour\socialimages\fields;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use nineteenninetyfour\socialimages\models\SocialImageValue;
use nineteenninetyfour\socialimages\Plugin;
use yii\db\Schema;

/**
 * The entry's social image: a preview of it (made in the background, with a
 * button to remake it), a choice of template, and an image to use instead.
 */
class SocialImage extends Field
{
    /** Volumes the override image can come from (sources, as Assets fields take them), or "*". */
    public string|array|null $sources = '*';

    public static function displayName(): string
    {
        return Craft::t('social-images', 'Social image');
    }

    public static function icon(): string
    {
        return 'image';
    }

    public static function phpType(): string
    {
        return SocialImageValue::class;
    }

    public static function dbType(): array|string|null
    {
        return Schema::TYPE_JSON;
    }

    public function normalizeValue(mixed $value, ?ElementInterface $element): mixed
    {
        if ($value instanceof SocialImageValue) {
            return $value;
        }

        if (is_string($value)) {
            $value = Json::decodeIfJson($value);
        }

        $value = is_array($value) ? $value : [];
        $image = $value['imageId'] ?? null;

        // Element selects post a list of ids.
        if (is_array($image)) {
            $image = reset($image) ?: null;
        }

        $template = is_string($value['template'] ?? null) && $value['template'] !== '' ? $value['template'] : null;

        return new SocialImageValue([
            'template' => $template !== null && preg_match('/^[a-z0-9_]{1,60}$/', $template) ? $template : null,
            'imageId' => is_numeric($image) ? (int) $image : null,
        ]);
    }

    public function serializeValue(mixed $value, ?ElementInterface $element): mixed
    {
        return $value instanceof SocialImageValue ? $value->toArray() : null;
    }

    public function isValueEmpty(mixed $value, ElementInterface $element): bool
    {
        return ! $value instanceof SocialImageValue || ($value->template === null && $value->imageId === null);
    }

    protected function inputHtml(mixed $value, ?ElementInterface $element, bool $inline): string
    {
        /** @var SocialImageValue $value */
        $plugin = Plugin::getInstance();
        $templates = [['label' => Craft::t('social-images', 'The settings’ choice'), 'value' => '']];

        foreach ($plugin->templates->all() as $handle => $template) {
            $templates[] = ['label' => $template->title(), 'value' => $handle];
        }

        $templates[] = ['label' => Craft::t('social-images', 'None'), 'value' => 'none'];

        $preview = null;

        if ($element instanceof Entry && $element->id && ! $element->getIsUnpublishedDraft()) {
            $canonical = $element->getCanonical();
            $preview = [
                'statusUrl' => UrlHelper::actionUrl('social-images/images/status', ['entryId' => $canonical->id, 'siteId' => $canonical->siteId]),
                'regenerateUrl' => UrlHelper::actionUrl('social-images/images/regenerate'),
                'entryId' => $canonical->id,
                'siteId' => $canonical->siteId,
            ];
        }

        return Craft::$app->getView()->renderTemplate('social-images/_field/input', [
            'field' => $this,
            'name' => $this->handle,
            'value' => $value,
            'templates' => $templates,
            'image' => $value?->getImage(),
            'preview' => $preview,
            'enabled' => $plugin->getSettings()->enabled,
            'sources' => $this->sources,
            'elementType' => Asset::class,
        ]);
    }

    public function getSettingsHtml(): ?string
    {
        return null;
    }
}
