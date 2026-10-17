<?php

namespace nineteenninetyfour\socialimages\fields;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\helpers\Json;
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

    public function getElementValidationRules(): array
    {
        return ['validateImage'];
    }

    /**
     * The image chosen instead must be an image, from the volumes the field
     * offers, that the person choosing it can see. Checked only when it
     * changes, so someone else saving the entry later isn't stopped by a
     * choice they didn't make.
     */
    public function validateImage(ElementInterface $element): void
    {
        $value = $element->getFieldValue($this->handle);

        if (! $value instanceof SocialImageValue || ! $value->imageId || ! $element->isFieldDirty($this->handle)) {
            return;
        }

        $asset = Asset::find()->id($value->imageId)->status(null)->one();
        $user = Craft::$app->getUser()->getIdentity();

        $allowed = $asset
            && $asset->kind === Asset::KIND_IMAGE
            && ($this->sources === '*' || $this->sources === null || in_array('volume:'.$asset->getVolume()->uid, (array) $this->sources, true))
            && (! $user || Craft::$app->getElements()->canView($asset, $user));

        if (! $allowed) {
            $element->addError($this->handle, Craft::t('social-images', 'That image can’t be used here.'));
        }
    }

    public function getSettingsHtml(): ?string
    {
        return null;
    }
}
