<?php

namespace Webtack\Ecms\Content\FieldTypes;

use Webtack\Ecms\Content\PresentContext;
use Webtack\Ecms\Models\ContentField;

/**
 * Describes one kind of field value: where it is stored, how it is validated and converted,
 * and which filter operators it supports.
 *
 * Register an implementation in `config/content.php` (`field_types`) to add a new type.
 *
 * @example
 *   $type = app(FieldTypeRegistry::class)->get('integer');
 *   $type->table();                 // 'content_values_integer'
 *   $type->prepare('42');           // 42
 *   $type->operators();             // ['eq', 'in', 'gt', 'gte', 'lt', 'lte', 'between']
 */
interface FieldType
{
    /** Registry key, stored in `content_fields.type`. */
    public function key(): string;

    /** Name of the table holding the values of this type. */
    public function table(): string;

    /**
     * Laravel validation rules for ONE value of the field (multiple fields validate each element).
     *
     * @return array<int, mixed>
     */
    public function rules(ContentField $field): array;

    /** Convert a value read from the database to its PHP type. */
    public function cast(mixed $value): mixed;

    /** Convert a validated input value to what is written to the database column. */
    public function prepare(mixed $value): mixed;

    /**
     * Filter operators supported by this type: eq, in (any of a list), gt, gte, lt, lte, between, contains.
     *
     * @return array<int, string>
     */
    public function operators(): array;

    /** Whether the type takes part in text search (LIKE). */
    public function searchable(): bool;

    /**
     * Whether ONE input value counts as "no value" (it is then cleared or dropped from a list).
     * The default is `null` or `''`; a type overrides it when its empty value has another shape.
     */
    public function isBlank(mixed $value): bool;

    /**
     * The public form of ONE value as read by `ContentValueReader`: data a template prints without a query and
     * without knowing how the value is stored (a file URL instead of `{uuid}.{ext}`, a term name instead of its
     * uuid, ...). A multiple field calls this once per element.
     *
     * Return `null` for a value that must not be shown (for example a reference to a node that is not visible);
     * the renderer drops it from the field's items.
     *
     * @return mixed A value of the PHP type of the field, or a structure documented by the type.
     *
     * @example
     *   $type = app(FieldTypeRegistry::class)->get('image');
     *   $type->present('0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.png', $field, app(PresentContext::class));
     *   // "/storage/0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.png"
     */
    public function present(mixed $value, ContentField $field, PresentContext $context): mixed;
}
