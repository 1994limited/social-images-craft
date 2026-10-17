<?php

namespace nineteenninetyfour\socialimages\rendering;

use Craft;
use craft\base\ElementInterface;
use craft\elements\Asset;
use craft\elements\db\ElementQueryInterface;
use craft\elements\Entry;
use craft\elements\User;
use craft\fields\data\MultiOptionsFieldData;
use craft\fields\data\SingleOptionFieldData;
use DateTimeInterface;
use Illuminate\Support\Collection;
use nineteenninetyfour\socialimages\support\Handles;
use NineteenNinetyFour\SocialImages\Core\Rendering\Values;
use Stringable;
use Throwable;

/**
 * What a token means in Craft.
 *
 *   {title} {slug}               the entry's own
 *   {summary}                    any custom field, by handle
 *   {author:name} {author.name}  the first author, or a field of the first
 *                                element a relation field points at
 *   {section:title} {section:handle}   ({collection:…} means the same)
 *   {site:name} {site:url} {site:language} {site:handle}
 *   {date} {date:j M Y}          the post date (or last update)
 *   {url} {domain} {path}
 *
 * Only custom fields and a short list of each element's attributes can be
 * read, so a template can't show, say, a user's email address.
 */
class CraftValues implements Values
{
    /** Attributes a token may read, besides custom fields. */
    protected const ATTRIBUTES = [
        Entry::class => ['title', 'slug', 'uri'],
        User::class => ['name', 'fullName', 'firstName', 'lastName', 'friendlyName', 'username'],
        Asset::class => ['title', 'alt', 'filename'],
        '*' => ['title', 'slug'],
    ];

    public function read(object $entry, string $token): string
    {
        if (! $entry instanceof Entry) {
            return '';
        }

        [$name, $argument] = array_pad(explode(':', $token, 2), 2, null);
        $name = trim($name);

        return match ($name) {
            'site' => $this->site($entry, $argument ?? 'name'),
            'section', 'collection' => $argument === 'handle' ? (string) $entry->getSection()?->handle : (string) $entry->getSection()?->name,
            'date' => $this->date($entry, $argument),
            'url' => (string) $entry->getUrl(),
            'domain' => (string) parse_url((string) $entry->getUrl(), PHP_URL_HOST),
            'path' => '/'.ltrim((string) $entry->uri === '__home__' ? '' : (string) $entry->uri, '/'),
            'field' => $argument ? $this->field($entry, $argument) : '',
            default => $this->field($entry, $argument !== null ? $name.'.'.$argument : $name),
        };
    }

    public function formatDate(DateTimeInterface $date, string $format): string
    {
        return Craft::$app->getFormatter()->asDate($date, 'php:'.$format);
    }

    /**
     * A field or allowed attribute by handle, or one of the first element a
     * relation points at ("author.name"), as text.
     */
    public function field(ElementInterface $element, string $handle): string
    {
        [$handle, $rest] = array_pad(explode('.', str_replace(':', '.', trim($handle)), 2), 2, null);

        if (! preg_match('/^[a-zA-Z0-9_]+$/', $handle)) {
            return '';
        }

        // {author} on its own is the author's name.
        if ($handle === 'author' && $element instanceof Entry && ! $this->hasField($element, 'author')) {
            $author = $element->getAuthor();

            return $author ? $this->field($author, $rest ?? 'name') : '';
        }

        $value = $this->value($element, $handle);

        if ($rest !== null) {
            $related = $this->first($value);

            return $related instanceof ElementInterface ? $this->field($related, $rest) : '';
        }

        return $this->text($value);
    }

    /**
     * A value as one line of text.
     */
    public function text(mixed $value): string
    {
        if ($value instanceof ElementQueryInterface) {
            $value = $value->all();
        }

        return match (true) {
            $value === null, is_bool($value) => '',
            $value instanceof SingleOptionFieldData => (string) $value->label,
            $value instanceof MultiOptionsFieldData => implode(', ', array_filter(array_map(fn ($option) => $option->selected ? $option->label : null, iterator_to_array($value)))),
            $value instanceof DateTimeInterface => $this->formatDate($value, 'j F Y'),
            $value instanceof User => (string) ($value->fullName ?: $value->friendlyName),
            $value instanceof Asset => (string) ($value->alt ?: $value->title),
            $value instanceof ElementInterface => (string) $value->title,
            $value instanceof Collection => $this->list($value->all()),
            is_array($value) => $this->list($value),
            is_scalar($value), $value instanceof Stringable => (string) $value,
            default => '',
        };
    }

    protected function list(array $values): string
    {
        $texts = [];

        foreach ($values as $item) {
            // Matrix rows and table rows aren't text.
            if (is_array($item) || ($item instanceof Entry && $item->fieldId !== null)) {
                continue;
            }

            if (($text = $this->text($item)) !== '') {
                $texts[] = $text;
            }
        }

        return implode(', ', $texts);
    }

    protected function value(ElementInterface $element, string $handle): mixed
    {
        try {
            if ($field = Handles::field($element, $handle)) {
                return $element->getFieldValue($field);
            }

            if (in_array($handle, $this->attributes($element), true)) {
                return $handle === 'name' && $element instanceof User ? $element->getName() : $element->$handle;
            }
        } catch (Throwable) {
        }

        return null;
    }

    protected function hasField(ElementInterface $element, string $handle): bool
    {
        return Handles::field($element, $handle) !== null;
    }

    /**
     * @return array<int, string>
     */
    protected function attributes(ElementInterface $element): array
    {
        foreach (self::ATTRIBUTES as $class => $attributes) {
            if ($class !== '*' && $element instanceof $class) {
                return $attributes;
            }
        }

        return self::ATTRIBUTES['*'];
    }

    protected function first(mixed $value): mixed
    {
        if ($value instanceof ElementQueryInterface) {
            return $value->one();
        }

        if ($value instanceof Collection) {
            return $value->first();
        }

        return is_array($value) ? (reset($value) ?: null) : $value;
    }

    protected function site(Entry $entry, string $part): string
    {
        $site = $entry->getSite();

        return match ($part) {
            'url' => (string) $site->getBaseUrl(),
            'language', 'locale' => (string) $site->language,
            'handle' => (string) $site->handle,
            default => (string) $site->getName(),
        };
    }

    protected function date(Entry $entry, ?string $format): string
    {
        $date = $entry->postDate ?? $entry->dateUpdated;

        return $date ? $this->formatDate($date, $format ?: 'j F Y') : '';
    }
}
