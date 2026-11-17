<?php

namespace Webtack\Ecms\Content;

/**
 * The single list of system fields: the fields the application creates itself with a content type
 * (`title`, the SEO metadata and the section fields).
 *
 * It is the source of truth for four things: which fields a new type of a kind gets, which keys a
 * user may not take for a new field, which values a node or a type may hold (the owner of a field),
 * and which fields a backfill adds to existing types. A field without an entry here is an ordinary
 * field and belongs to nodes only.
 *
 * Every definition has a `key`, a `panel` (the admin panel that groups the field apart from the
 * ordinary fields: `seo` or `section`; null for none), `type` (field type key), `name` (English, as stored; the client
 * translates labels by key), `owners` (type kind => `node` and/or `type`: whose values the field
 * holds; a kind that is absent does not get the field), `required` and `weight`. Every system field is single and
 * translatable.
 *
 * @phpstan-type Definition array{key: string, panel: string|null, type: string, name: string, owners: array<string, list<string>>, required: bool, weight: int}
 *
 * @example
 *   $fields = new SystemFields;
 *   $fields->forKind('page');                        // title, meta_title, meta_description, meta_image
 *   $fields->owns('content', 'section_title', 'type'); // true
 *   $fields->owns('content', 'section_title', 'node'); // false
 *   $fields->owns('content', 'price', 'node');         // true (an ordinary field belongs to nodes)
 */
class SystemFields
{
    public const OWNER_NODE = 'node';

    public const OWNER_TYPE = 'type';

    /** @var list<Definition> */
    private const DEFINITIONS = [
        ['key' => 'title', 'panel' => null, 'type' => 'string', 'name' => 'Title', 'owners' => ['content' => [self::OWNER_NODE], 'page' => [self::OWNER_NODE], 'block' => [self::OWNER_NODE]], 'required' => true, 'weight' => 0],
        ['key' => 'meta_title', 'panel' => 'seo', 'type' => 'string', 'name' => 'Meta title', 'owners' => ['content' => [self::OWNER_NODE, self::OWNER_TYPE], 'page' => [self::OWNER_NODE, self::OWNER_TYPE]], 'required' => false, 'weight' => 1],
        ['key' => 'meta_description', 'panel' => 'seo', 'type' => 'text', 'name' => 'Meta description', 'owners' => ['content' => [self::OWNER_NODE, self::OWNER_TYPE], 'page' => [self::OWNER_NODE, self::OWNER_TYPE]], 'required' => false, 'weight' => 2],
        ['key' => 'meta_image', 'panel' => 'seo', 'type' => 'image', 'name' => 'Meta image', 'owners' => ['content' => [self::OWNER_NODE, self::OWNER_TYPE], 'page' => [self::OWNER_NODE, self::OWNER_TYPE]], 'required' => false, 'weight' => 3],
        ['key' => 'section_title', 'panel' => 'section', 'type' => 'string', 'name' => 'Section title', 'owners' => ['content' => [self::OWNER_TYPE]], 'required' => false, 'weight' => 4],
        ['key' => 'section_description', 'panel' => 'section', 'type' => 'richtext', 'name' => 'Section description', 'owners' => ['content' => [self::OWNER_TYPE]], 'required' => false, 'weight' => 5],
        ['key' => 'section_cover', 'panel' => 'section', 'type' => 'image', 'name' => 'Section cover', 'owners' => ['content' => [self::OWNER_TYPE]], 'required' => false, 'weight' => 6],
    ];

    /**
     * Every system field definition.
     *
     * @return list<Definition>
     */
    public function all(): array
    {
        return self::DEFINITIONS;
    }

    /**
     * The system fields a type of the given kind gets, in weight order.
     *
     * @return list<Definition>
     *
     * @example
     *   array_column((new SystemFields)->forKind('block'), 'key'); // ['title']
     */
    public function forKind(string $kind): array
    {
        return array_values(array_filter(self::DEFINITIONS, fn (array $d) => isset($d['owners'][$kind])));
    }

    /**
     * The definition of a system field, or null for an ordinary key.
     *
     * @return Definition|null
     */
    public function find(string $key): ?array
    {
        foreach (self::DEFINITIONS as $definition) {
            if ($definition['key'] === $key) {
                return $definition;
            }
        }

        return null;
    }

    /**
     * The admin panel of a field: `seo` for the metadata fields, `section` for the section fields,
     * null for `title` and ordinary fields.
     *
     * @example
     *   (new SystemFields)->panel('meta_title'); // 'seo'
     */
    public function panel(string $key): ?string
    {
        return $this->find($key)['panel'] ?? null;
    }

    /**
     * Keys of all system fields.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        return array_column(self::DEFINITIONS, 'key');
    }

    /**
     * Keys no user may take for a new field: the system fields except `title`, which every type
     * already has and which the key uniqueness rule rejects.
     *
     * @return list<string>
     *
     * @example
     *   (new SystemFields)->reservedKeys(); // ['meta_title', ..., 'section_cover']
     */
    public function reservedKeys(): array
    {
        return array_values(array_diff($this->keys(), ['title']));
    }

    /**
     * Whether the fields with this key hold values of the given owner. A key without a definition
     * is an ordinary field and belongs to nodes only; a system field belongs to the owners of its
     * definition for the kind of its type.
     *
     * With a null kind the answer holds for a field that exists in some type of any kind: a system
     * field exists only in the kinds that get it, so the kind need not be known to tell node fields
     * from type fields.
     *
     * @param  self::OWNER_*  $owner
     *
     * @example
     *   $fields->owns('page', 'meta_title', 'type'); // true: page types hold metadata values
     *   $fields->owns(null, 'meta_title', 'type');   // true: some kind does
     */
    public function owns(?string $kind, string $key, string $owner): bool
    {
        $definition = $this->find($key);

        if ($definition === null) {
            return $owner === self::OWNER_NODE;
        }

        $owners = $kind === null
            ? array_merge(...array_values($definition['owners']))
            : ($definition['owners'][$kind] ?? []);

        return in_array($owner, $owners, true);
    }
}
