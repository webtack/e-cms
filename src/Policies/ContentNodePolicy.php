<?php

namespace Webtack\Ecms\Policies;

use Webtack\Ecms\Access\AccessResolver;
use Webtack\Ecms\Contracts\EcmsUser;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;

/**
 * Access to the nodes of a type by the level of the user's role on that type (`node:{key}`).
 *
 * `none`: nothing (the list, the forms and every write answer 403). `own`: create, and change or delete only
 * nodes the user authored. `any`: everything. Publication fields follow the same right as the node.
 *
 * @example
 *   $this->authorize('viewAny', [ContentNode::class, $contentType]);
 *   $this->authorize('update', $node);
 */
class ContentNodePolicy
{
    public function __construct(private readonly AccessResolver $access) {}

    /** The list and the forms of a type. */
    public function viewAny(EcmsUser $user, ContentType $type): bool
    {
        return $this->access->nodeLevel($user, $type) !== AccessResolver::NONE;
    }

    public function create(EcmsUser $user, ContentType $type): bool
    {
        return $this->access->nodeLevel($user, $type) !== AccessResolver::NONE;
    }

    public function update(EcmsUser $user, ContentNode $node): bool
    {
        return $this->owns($user, $node);
    }

    public function delete(EcmsUser $user, ContentNode $node): bool
    {
        return $this->owns($user, $node);
    }

    private function owns(EcmsUser $user, ContentNode $node): bool
    {
        return $this->access->canEditNode($user, $node->content_type, $node->user_id);
    }
}
