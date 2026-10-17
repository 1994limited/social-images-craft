<?php

namespace nineteenninetyfour\socialimages\support;

use Craft;
use craft\elements\Entry;
use craft\elements\User;
use craft\fields\Assets;
use craft\fields\BaseRelationField;
use craft\fields\Checkboxes;
use craft\fields\Color;
use craft\fields\Date;
use craft\fields\Dropdown;
use craft\fields\Email;
use craft\fields\Link;
use craft\fields\Number;
use craft\fields\PlainText;
use craft\fields\RadioButtons;
use craft\fields\Url;
use craft\models\Section;
use nineteenninetyfour\socialimages\fields\SocialImage;

/**
 * What the designer offers to bind layers to: each section's fields, and
 * entries to preview with. Sections are called collections here, as the
 * designer (shared with Statamic) calls them.
 */
class Catalog
{
    /** Field types whose value reads as text, besides rich text (matched by name). */
    protected const TEXT = [PlainText::class, Number::class, Date::class, Dropdown::class, RadioButtons::class, Checkboxes::class, Color::class, Email::class, Url::class, Link::class];

    /**
     * @return array<int, array{handle: string, title: string, fields: array<int, array{handle: string, display: string, type: string, kind: string}>}>
     */
    public function collections(): array
    {
        $sections = array_filter(Craft::$app->getEntries()->getAllSections(), fn (Section $section) => $this->hasUrls($section));
        usort($sections, fn ($a, $b) => strcasecmp($a->name, $b->name));

        return array_values(array_map(fn (Section $section) => [
            'handle' => $section->handle,
            'title' => $section->name,
            'fields' => $this->fields($section),
        ], $sections));
    }

    /**
     * Entries to preview with, newest first. Each id is "{entry}:{site}".
     *
     * @return array<int, array{id: string, title: string, collection: string, site: string}>
     */
    public function entries(?string $search = null, ?string $section = null, ?User $user = null, int $limit = 20): array
    {
        $handles = array_column($this->collections(), 'handle');

        if (! $handles) {
            return [];
        }

        $query = Entry::find()
            ->section($section && in_array($section, $handles, true) ? $section : $handles)
            ->site('*')
            ->unique()
            ->status(null)
            ->orderBy(['postDate' => SORT_DESC])
            ->limit($limit * 3);

        if ($search) {
            $query->search('title:*'.str_replace(['*', '"'], '', $search).'*');
        }

        $entries = [];

        foreach ($query->all() as $entry) {
            if (! $user || Craft::$app->getElements()->canView($entry, $user)) {
                $entries[] = $this->entry($entry);
            }

            if (count($entries) >= $limit) {
                break;
            }
        }

        return $entries;
    }

    public function entry(Entry $entry): array
    {
        return [
            'id' => $entry->id.':'.$entry->siteId,
            'title' => (string) $entry->title,
            'collection' => (string) $entry->getSection()?->name,
            'site' => $entry->getSite()->handle,
        ];
    }

    /**
     * The entry a "{entry}:{site}" id names.
     */
    public function find(mixed $id): ?Entry
    {
        if (! is_string($id) || ! preg_match('/^(\d+)(?::(\d+))?$/', $id, $matches)) {
            return null;
        }

        return Entry::find()->id((int) $matches[1])->siteId(isset($matches[2]) ? (int) $matches[2] : '*')->status(null)->one();
    }

    protected function hasUrls(Section $section): bool
    {
        foreach ($section->getSiteSettings() as $settings) {
            if ($settings->hasUrls) {
                return true;
            }
        }

        return false;
    }

    protected function fields(Section $section): array
    {
        $fields = [];

        foreach ($section->getEntryTypes() as $type) {
            foreach ($type->getFieldLayout()->getCustomFields() as $field) {
                if ($field instanceof SocialImage || isset($fields[$field->handle])) {
                    continue;
                }

                $kind = match (true) {
                    $field instanceof Assets => 'image',
                    $field instanceof BaseRelationField => 'related',
                    $this->isText($field) => 'text',
                    default => 'other',
                };

                if ($kind !== 'other') {
                    $fields[$field->handle] = [
                        'handle' => $field->handle,
                        'display' => (string) $field->name,
                        'type' => $field::displayName(),
                        'kind' => $kind,
                    ];
                }
            }
        }

        return array_values($fields);
    }

    protected function isText(object $field): bool
    {
        foreach (self::TEXT as $class) {
            if ($field instanceof $class) {
                return true;
            }
        }

        // CKEditor and Redactor, without depending on them.
        return (bool) preg_match('/ckeditor|redactor/i', $field::class);
    }
}
