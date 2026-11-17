<?php

namespace Webtack\Ecms\Content;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;

/**
 * Who a stored value belongs to: a content node or a content type itself.
 *
 * It carries what `ContentValueWriter` and `ContentValueReader` need to address the rows of one
 * owner in the `content_values_*` tables (`entity`, `content_node_id`, `content_node_uuid`,
 * `content_type`), the file-relation owner (`media_relations.owner_type`/`owner_id`) and the
 * fields that owner may hold (decided by {@see SystemFields::owns()}, not by a column).
 *
 * @example
 *   $owner = ValueOwner::forNode($node);
 *   $owner->row();                              // ['entity' => 'node', 'content_node_id' => 5, ...]
 *   ValueOwner::forType($type)->fields($all);   // only the fields whose values the type holds
 */
final class ValueOwner
{
    private function __construct(
        /** `node` or `type` (the `entity` column). */
        public readonly string $entity,
        /** Key of the type: the node's type, or the type itself. */
        public readonly string $contentType,
        /** Kind of the type when it is known (a type owner); null for node owners, whose fields do not depend on it. */
        public readonly ?string $kind,
        public readonly ?int $nodeId,
        public readonly ?string $nodeUuid,
        /** `media_relations.owner_type` and `owner_id`. */
        public readonly string $mediaOwnerType,
        public readonly int $mediaOwnerId,
    ) {}

    /**
     * The owner of the values of a node.
     */
    public static function forNode(ContentNode $node): self
    {
        return new self(
            SystemFields::OWNER_NODE,
            $node->content_type,
            null,
            $node->id,
            $node->uuid,
            ContentNode::MEDIA_OWNER,
            $node->id,
        );
    }

    /**
     * The owner of the values of nodes of a type, before a node exists (creation checks).
     * It has no node columns, so it can validate but not address rows.
     */
    public static function forNodesOfType(string $contentType): self
    {
        return new self(SystemFields::OWNER_NODE, $contentType, null, null, null, ContentNode::MEDIA_OWNER, 0);
    }

    /**
     * The owner of the values of the type itself.
     */
    public static function forType(ContentType $type): self
    {
        return new self(SystemFields::OWNER_TYPE, $type->key, $type->kind, null, null, ContentType::MEDIA_OWNER, $type->id);
    }

    public function isType(): bool
    {
        return $this->entity === SystemFields::OWNER_TYPE;
    }

    /**
     * Whether the field with this key holds values of this owner.
     */
    public function owns(string $fieldKey): bool
    {
        return app(SystemFields::class)->owns($this->kind, $fieldKey, $this->entity);
    }

    /**
     * Keep only the fields this owner may hold.
     *
     * @param  Collection<string, ContentField>  $fields  Fields of the owner's type by key.
     * @return Collection<string, ContentField>
     */
    public function fields(Collection $fields): Collection
    {
        return $fields->filter(fn (ContentField $field) => $this->owns($field->key));
    }

    /**
     * The owner columns of a `content_values_*` row.
     *
     * @return array{entity: string, content_node_id: int|null, content_node_uuid: string|null, content_type: string}
     */
    public function row(): array
    {
        return [
            'entity' => $this->entity,
            'content_node_id' => $this->nodeId,
            'content_node_uuid' => $this->nodeUuid,
            'content_type' => $this->contentType,
        ];
    }

    /**
     * Restrict a query on a value table to the rows of this owner.
     */
    public function scope(Builder $query): Builder
    {
        $query->where('entity', $this->entity)->where('content_type', $this->contentType);

        return $this->isType() ? $query->whereNull('content_node_id') : $query->where('content_node_id', $this->nodeId);
    }
}
