<?php

namespace Webtack\Ecms\Files\Owners;

/**
 * Describes the owners of one type to the media library: a label and, when the owner has one, the
 * admin page where it is edited. Registered in `config('files.owners')` as `owner_type => class`.
 *
 * @example
 *   app(ContentNodeOwner::class)->describe([5, 6]);
 *   // [5 => ['label' => 'Hello', 'url' => '/admin/content-types/article/nodes/0190.../edit'], 6 => ...]
 */
interface MediaOwner
{
    /**
     * Describe the owners with these ids in one pass (no query per owner).
     *
     * @param  list<int>  $ids
     * @return array<int, array{label: string, url: string|null}> Keyed by owner id; a missing owner is absent.
     */
    public function describe(array $ids): array;
}
