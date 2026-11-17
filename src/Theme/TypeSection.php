<?php

namespace Webtack\Ecms\Theme;

use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Content\ContentValueReader;
use Webtack\Ecms\Content\HtmlSanitizer;
use Webtack\Ecms\Content\PresentContext;
use Webtack\Ecms\Files\FileStorage;
use Webtack\Ecms\Files\Rules\FileValue;
use Webtack\Ecms\Models\ContentType;

/**
 * The public title of a content type: its `section_title` value (the "Display" tab of the type).
 *
 * {@see section()} gives the value alone: in the reading language ({@see PresentContext::$lang}), then in `default`
 * (the base language), or null when both are empty. {@see title()} adds the last step: {@see ContentType::$name}. An
 * empty value counts as absent. A type of kind `page` or `block` holds no section values, so `section()` is null for
 * it without a read. The result is a plain string; the template escapes it. Results are memoised per request by type
 * key (the service is scoped), so the list page, the breadcrumbs and the page meta share one read.
 *
 * @example
 *   app(TypeSection::class)->title($news);   // "Новини" (section_title), or "News" (name) when it has none
 *   app(TypeSection::class)->section($news); // "Новини", or null when it has none
 */
class TypeSection
{
    /** @var array<string, string|null> type key => section title */
    private array $sections = [];

    /** @var array<string, string|null> `{type key}:{field key}` => stored value of the section field */
    private array $raw = [];

    public function __construct(
        private readonly ContentValueReader $reader,
        private readonly PresentContext $context,
        private readonly HtmlSanitizer $html,
        private readonly FileStorage $files,
    ) {}

    public function title(ContentType $type): string
    {
        return $this->section($type) ?? $type->name;
    }

    /** The Section title of the type (reading language, then base), or null when it has none (no `name` fallback). */
    public function section(ContentType $type): ?string
    {
        if (! array_key_exists($type->key, $this->sections)) {
            $this->sections[$type->key] = $this->resolve($type, 'section_title');
        }

        return $this->sections[$type->key];
    }

    /** The Section description of the type as sanitized HTML (print it unescaped), or null when it has none. */
    public function description(ContentType $type): ?string
    {
        $value = $this->resolve($type, 'section_description');

        return $value === null ? null : ($this->html->clean($value) ?: null);
    }

    /** The public URL of the Section cover of the type, or null when it has none. */
    public function cover(ContentType $type): ?string
    {
        $value = $this->resolve($type, 'section_cover');

        return $value !== null && preg_match(FileValue::PATTERN, $value) === 1 ? $this->files->urlForValue('image', $value) : null;
    }

    private function resolve(ContentType $type, string $key): ?string
    {
        if ($type->kind !== 'content') {
            return null;
        }

        $cache = "{$type->key}:{$key}";

        if (array_key_exists($cache, $this->raw)) {
            return $this->raw[$cache];
        }

        foreach (array_unique([$this->context->lang, 'default']) as $lang) {
            $value = $this->read($type, $key, $lang);

            if ($value !== null) {
                return $this->raw[$cache] = $value;
            }
        }

        return $this->raw[$cache] = null;
    }

    private function read(ContentType $type, string $key, string $lang): ?string
    {
        try {
            $value = $this->reader->loadForType($type, [$key], $lang)[$key] ?? null;
        } catch (ValidationException) {
            // The type has no such field (damaged data) or the language is not readable: treat as absent.
            return null;
        }

        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
