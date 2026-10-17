<?php

namespace nineteenninetyfour\socialimages\models;

use craft\base\Model;
use craft\elements\Asset;

/**
 * A Social image field's value: the template the entry chose (null for the
 * settings' choice, "none" for no image) and an image to use instead.
 */
class SocialImageValue extends Model
{
    public ?string $template = null;

    public ?int $imageId = null;

    private Asset|false|null $image = null;

    public function getImage(): ?Asset
    {
        if ($this->image === null) {
            $this->image = $this->imageId ? (Asset::find()->id($this->imageId)->status(null)->one() ?? false) : false;
        }

        return $this->image ?: null;
    }

    public function toArray(array $fields = [], array $expand = [], $recursive = true): array
    {
        return ['template' => $this->template, 'imageId' => $this->imageId];
    }
}
