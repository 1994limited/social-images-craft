<?php

namespace nineteenninetyfour\socialimages\support;

use craft\base\ElementInterface;
use craft\helpers\StringHelper;

/**
 * Finds an element's field by the handle a template uses. Templates are
 * shared between Craft and Statamic, where handles are snake_case, so
 * "featured_image" finds a featuredImage field, and the other way round.
 */
class Handles
{
    public static function field(ElementInterface $element, string $handle): ?string
    {
        $layout = $element->getFieldLayout();

        if (! $layout) {
            return null;
        }

        foreach (array_unique([$handle, StringHelper::toCamelCase($handle), StringHelper::toSnakeCase($handle)]) as $candidate) {
            if ($layout->getFieldByHandle($candidate) !== null) {
                return $candidate;
            }
        }

        return null;
    }
}
