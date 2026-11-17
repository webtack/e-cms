<?php

namespace Webtack\Ecms\Theme;

use Illuminate\Support\Collection;
use Webtack\Ecms\Content\FieldTypeRegistry;
use Webtack\Ecms\Content\FieldTypes\FieldType;
use Webtack\Ecms\Content\FieldTypes\PreloadsReferences;
use Webtack\Ecms\Content\PresentContext;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;

/**
 * Renders a field of a node with the theme: turns the raw values into their public form
 * ({@see FieldType::present()}) and hands them to the first existing template of
 * eight candidates, from the most specific to the most general:
 *
 *     {t}/field-{ftype}--{key}   {t}/field-{ftype}   {t}/field--{key}   {t}/field
 *     fields/field-{ftype}--{key}   fields/field-{ftype}   fields/field--{key}   fields/field
 *
 * `{t}` is the key of the node's content type, `{ftype}` the field type, `{key}` the field key.
 *
 * A template always receives `$items`, an array of public values, also for a field that is not multiple (then it
 * holds one element), so one template serves both shapes. A field without a value renders `''` and no template
 * runs. The hooks `preprocess('field')` and `preprocess('field.{ftype}')` (in that order) may change the
 * variables, and `suggest('field')` / `suggest('field.{ftype}')` the candidates.
 *
 * The `$field` a template receives is a detached copy whose `name` is the label in the reading language (the
 * translated label, else the stored name), so templates that print `$field->name` as the label need no change. The
 * copy is never saved; the stored name is untouched.
 *
 * Each item of a `node_ref` field also gets `content`, the HTML of the target's block template or card (see {@see NodeContentRenderer}).
 *
 * A template also receives `$values`, the values of the same node by field key as the caller already has them
 * (`[]` when a field is rendered without them), so it can use another field of the record, such as `title`,
 * without a query.
 *
 * The Blade directives `@field($values, 'key')` and `@fields($values, ['a', 'b'])` call this class with the
 * variables of the template (`$node`, `$type`, `$fields`), see {@see self::renderInScope()}.
 *
 * @example
 *   $html = app(FieldRenderer::class)->render($field, $values['cover'], $node, $type);
 *   // <div class="field field-image field--cover">...</div>, or '' when the field has no value
 * @example In a template
 *
 *   @foreach ($fields as $key => $field) @field($values, $key) @endforeach
 *
 * @example In a field template (`fields/field-youtube--youtube.blade.php`)
 *
 *   {{ $item['title'] ?: ($values['title'] ?? $field->name) }}
 */
class FieldRenderer
{
    /** @var array<string, Collection<string, ContentField>> type key => fields by key */
    private array $typeFields = [];

    public function __construct(
        private readonly ThemeManager $themes,
        private readonly FieldTypeRegistry $registry,
        private readonly PresentContext $context,
    ) {}

    /**
     * The template candidates of a field, most specific first.
     *
     * @return list<string>
     *
     * @example app(FieldRenderer::class)->candidates($cover, 'news'); // ['news/field-image--cover', 'news/field-image', ..., 'fields/field']
     */
    public function candidates(ContentField $field, string $typeKey): array
    {
        $candidates = [];

        foreach ([$typeKey.'/field', 'fields/field'] as $base) {
            array_push(
                $candidates,
                "{$base}-{$field->type}--{$field->key}",
                "{$base}-{$field->type}",
                "{$base}--{$field->key}",
                $base,
            );
        }

        return $candidates;
    }

    /**
     * Render one field.
     *
     * @param  mixed  $raw  The value as `ContentValueReader` returned it: a scalar or a composed array, a list for a multiple field.
     * @param  array<string, mixed>  $values  The values of the node by field key, given to the template as `$values`; `[]` when the caller has none.
     *
     * @example app(FieldRenderer::class)->render($field, $values['youtube'], $node, $type, $values); // the template can read $values['title']
     */
    public function render(ContentField $field, mixed $raw, ContentNode $node, ContentType $type, array $values = []): string
    {
        $this->preload([$field->key => $field], [[$field->key => $raw]]);
        $items = $this->items($field, $raw);

        if ($items === []) {
            return '';
        }

        if ($field->type === 'node_ref') {
            // Resolved lazily: the content renderer itself calls this class.
            $content = app(NodeContentRenderer::class);
            $items = array_map(fn (array $item) => $item + ['content' => $content->render($item['uuid'], $node, $item['url'] ?? null)], $items);
        }

        return $this->themes->render(
            $this->candidates($field, $type->key),
            [
                'field' => $this->labelled($field),
                'key' => $field->key,
                'ftype' => $field->type,
                'multiple' => $field->is_multiple,
                'items' => $items,
                'node' => $node,
                'type' => $type,
                'values' => $values,
            ],
            'field.'.$field->type,
        );
    }

    /**
     * A detached copy of the field whose `name` is the label in the reading language; the field itself when the
     * label is the stored name.
     */
    private function labelled(ContentField $field): ContentField
    {
        $label = $this->context->label($field);

        if ($label === $field->name) {
            return $field;
        }

        $copy = clone $field;
        $copy->setAttribute('name', $label);

        return $copy;
    }

    /**
     * Read the references of a whole page in batches before rendering, so a list of cards costs a fixed
     * number of queries. Safe to skip: a lone field is then loaded when it renders.
     *
     * @param  array<string, ContentField>  $fields  The fields to be rendered, by key.
     * @param  iterable<array<string, mixed>>  $valuesList  The values of each node (`key => raw value`).
     */
    public function preload(array $fields, iterable $valuesList): void
    {
        foreach ($fields as $key => $field) {
            $type = $this->registry->get($field->type);

            if (! $type instanceof PreloadsReferences) {
                continue;
            }

            $collected = [];

            foreach ($valuesList as $values) {
                $raw = $values[$key] ?? null;

                foreach ($field->is_multiple ? (is_array($raw) ? $raw : []) : [$raw] as $value) {
                    if (! $this->registry->get($field->type)->isBlank($value)) {
                        $collected[] = $value;
                    }
                }
            }

            $type->preload($collected, $field, $this->context);
        }
    }

    /**
     * Render the field `$key` of the node found in the template variables (`@field($values, 'key')`).
     * A key the type no longer has gives `''`, so a field deleted in the admin does not break the site.
     *
     * @param  array<string, mixed>  $scope  `get_defined_vars()` of the template: `$node`, `$type` and optionally `$fields`.
     * @param  array<string, mixed>  $values  The values of the node by field key.
     *
     * @throws ThemeException When the template has no `$node` or `$type`.
     */
    public function renderInScope(array $scope, array $values, string $key): string
    {
        [$node, $type] = $this->scopeNode($scope, 'field');
        $field = $this->findField($scope, $type, $key);

        return $field === null ? '' : $this->render($field, $values[$key] ?? null, $node, $type, $values);
    }

    /**
     * Render several fields in order (`@fields($values)` for every field of `$fields`, `@fields($values, ['a', 'b'])` for some).
     *
     * @param  array<string, mixed>  $scope  `get_defined_vars()` of the template.
     * @param  array<string, mixed>  $values
     * @param  list<string>|null  $keys  Field keys; `null` renders every field of `$fields`.
     *
     * @throws ThemeException When the template has no `$node`, `$type` or (without `$keys`) `$fields`.
     */
    public function renderManyInScope(array $scope, array $values, ?array $keys = null): string
    {
        [$node, $type] = $this->scopeNode($scope, 'fields');

        if ($keys === null && ! is_array($scope['fields'] ?? null)) {
            throw ThemeException::missingContext('fields', ['fields']);
        }

        $keys ??= array_keys($scope['fields']);
        $fields = [];

        foreach ($keys as $key) {
            if (($field = $this->findField($scope, $type, $key)) !== null) {
                $fields[$key] = $field;
            }
        }

        $this->preload($fields, [$values]);

        return implode('', array_map(
            fn (string $key) => $this->render($fields[$key], $values[$key] ?? null, $node, $type, $values),
            array_keys($fields),
        ));
    }

    /**
     * The public values of a field: blank ones dropped, the rest presented, the ones a type hides (`null`) dropped.
     *
     * @return list<mixed>
     */
    private function items(ContentField $field, mixed $raw): array
    {
        $type = $this->registry->get($field->type);
        $items = [];

        foreach ($field->is_multiple ? (is_array($raw) ? $raw : []) : [$raw] as $value) {
            if ($type->isBlank($value)) {
                continue;
            }

            $item = $type->present($value, $field, $this->context);

            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $scope
     * @return array{0: ContentNode, 1: ContentType}
     *
     * @throws ThemeException
     */
    private function scopeNode(array $scope, string $directive): array
    {
        $node = $scope['node'] ?? null;
        $type = $scope['type'] ?? null;
        $missing = array_keys(array_filter(['node' => ! $node instanceof ContentNode, 'type' => ! $type instanceof ContentType]));

        if ($missing !== []) {
            throw ThemeException::missingContext($directive, $missing);
        }

        return [$node, $type];
    }

    /**
     * @param  array<string, mixed>  $scope
     */
    private function findField(array $scope, ContentType $type, string $key): ?ContentField
    {
        $shown = $scope['fields'][$key] ?? null;

        if ($shown instanceof ContentField) {
            return $shown;
        }

        return ($this->typeFields[$type->key] ??= ContentField::query()->where('content_type', $type->key)->get()->keyBy('key'))[$key] ?? null;
    }
}
