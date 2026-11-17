<?php

namespace Webtack\Ecms\Content\FieldTypes;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Content\PresentContext;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Reference\ReferenceUsage;

/**
 * A reference to a content node: the value is the node's `uuid` (lower case), stored in
 * `content_values_string`.
 *
 * The field names the type of the nodes it may point at in `settings.content_type` (a type key).
 * A value must be the uuid of an existing node of that type. Filters (`eq`, `in`) only check that
 * the node exists.
 *
 * `content_values_string` has no foreign key to `content_nodes`, so a write locks the referenced
 * nodes ({@see LocksReferences}) and deleting a node checks its uses ({@see ReferenceUsage}).
 *
 * @example
 *   // field `related` with settings ['content_type' => 'article']
 *   app(ContentValueWriter::class)->write($node, ['related' => $article->uuid]);
 */
class NodeRefType extends AbstractFieldType implements LocksReferences, PreloadsReferences, ValidatesSettings
{
    public function key(): string
    {
        return 'node_ref';
    }

    public function table(): string
    {
        return 'content_values_string';
    }

    public function rules(ContentField $field): array
    {
        return ['string', 'uuid', function (string $attribute, mixed $value, Closure $fail) use ($field): void {
            $query = DB::table('content_nodes')->where('uuid', strtolower((string) $value));

            // Filters validate with an empty field (see ContentNodeQuery): any existing node then.
            if ($field->type !== null) {
                $query->where('content_type', (string) ($field->settings['content_type'] ?? ''));
            }

            if (! $query->exists()) {
                $fail('The :attribute must be an existing node'.($field->type !== null ? ' of the field\'s content type' : '').'.');
            }
        }];
    }

    public function prepare(mixed $value): mixed
    {
        return strtolower((string) $value);
    }

    public function cast(mixed $value): mixed
    {
        return (string) $value;
    }

    public function operators(): array
    {
        return ['eq', 'in'];
    }

    public function settingsErrors(array $settings, ?array $original, bool $hasValues): array
    {
        $type = $settings['content_type'] ?? null;

        if (array_diff(array_keys($settings), ['content_type']) !== []) {
            return ['content_type' => 'Only the content type can be set.'];
        }

        if (! is_string($type) || $type === '') {
            return ['content_type' => 'The content type is required.'];
        }

        if (! DB::table('content_types')->where('key', $type)->exists()) {
            return ['content_type' => 'The content type does not exist.'];
        }

        if ($original !== null && $hasValues && ($original['content_type'] ?? null) !== $type) {
            return ['content_type' => 'The content type of a field that has values cannot be changed.'];
        }

        return [];
    }

    public function lockReferences(array $prepared): void
    {
        $uuids = array_values(array_unique($prepared));
        $found = DB::table('content_nodes')->whereIn('uuid', $uuids)->sharedLock()->pluck('uuid');

        if ($found->count() !== count($uuids)) {
            throw ValidationException::withMessages(['value' => 'The node no longer exists.']);
        }
    }

    /**
     * The uuid, title and address of the target node; `null` when the node is not visible on the site (deleted,
     * disabled, scheduled or expired), so a template does not link to a page that answers 404.
     *
     * @return array{uuid: string, title: string|null, url: string|null}|null `uuid` is the key of the target (the field renderer adds its rendered `content`); `url` is `null` for a block, which has no address.
     *
     * @example $type->present($article->uuid, $field, $context); // ['uuid' => '0190…', 'title' => 'Hello', 'url' => '/news/hello']
     */
    public function present(mixed $value, ContentField $field, PresentContext $context): ?array
    {
        return is_string($value) ? $context->node($value) : null;
    }

    public function preload(array $values, ContentField $field, PresentContext $context): void
    {
        $context->preloadNodes(array_values(array_filter($values, 'is_string')));
    }

    protected function baseRules(): array
    {
        return ['string', 'uuid'];
    }
}
