<?php

namespace Webtack\Ecms\Theme;

use Illuminate\Support\Collection;
use Webtack\Ecms\Content\PublicDisplaySettings;
use Webtack\Ecms\Content\ValueOwner;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentType;

/**
 * Which fields of a content type a public template shows: the public display settings of the type
 * ({@see PublicDisplaySettings}) turned into `ContentField` models in the order of the setting.
 *
 * It is shared by the public controllers (pages, details, list cards) and by blocks, so they cannot disagree.
 * The fields of a type are read once per request (the service is scoped).
 *
 * @example
 *   $display = app(DisplayFields::class);
 *   $display->pageFields($type);                  // ['body' => ContentField, 'cover' => ContentField]
 *   $display->settings($type)['list_fields'];     // ['title', 'price']
 */
class DisplayFields
{
    /** @var array<string, Collection<string, ContentField>> type key => fields that hold node values, by key */
    private array $nodeFields = [];

    public function __construct(private readonly PublicDisplaySettings $display) {}

    /**
     * The settings of a type with the defaults applied (see {@see PublicDisplaySettings::resolve()}).
     *
     * @return array<string, mixed>
     */
    public function settings(ContentType $type): array
    {
        return $this->display->resolve($type, $this->nodeFields($type));
    }

    /**
     * The fields shown on a page, in a record's details and in a block: `page_fields`, or every added field.
     *
     * @return array<string, ContentField> By field key.
     */
    public function pageFields(ContentType $type): array
    {
        return $this->fields($type, $this->settings($type)['page_fields']);
    }

    /**
     * The fields of a type by the given keys, in that order; a key the type does not hold is skipped.
     *
     * @param  list<string>  $keys
     * @return array<string, ContentField> By field key.
     */
    public function fields(ContentType $type, array $keys): array
    {
        $all = $this->nodeFields($type);

        return collect($keys)
            ->filter(fn (string $key) => $all->has($key))
            ->mapWithKeys(fn (string $key) => [$key => $all[$key]])
            ->all();
    }

    /**
     * The fields that hold node values (not those of the type itself), by key.
     *
     * @return Collection<string, ContentField>
     */
    private function nodeFields(ContentType $type): Collection
    {
        return $this->nodeFields[$type->key] ??= ValueOwner::forNodesOfType($type->key)
            ->fields(ContentField::query()->where('content_type', $type->key)->get()->keyBy('key'));
    }
}
