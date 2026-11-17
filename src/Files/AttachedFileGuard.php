<?php

namespace Webtack\Ecms\Files;

use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Access\AccessResolver;
use Webtack\Ecms\Content\ValueOwner;
use Webtack\Ecms\Contracts\EcmsUser;
use Webtack\Ecms\Models\File;
use Webtack\Ecms\Models\MediaRelation;

/**
 * Refuses a node save that attaches a file the user may not attach.
 *
 * A node form has no picker of library files: new files get into a node by upload, which records the
 * uploader. A crafted request can still carry any existing file uuid, so the server checks it. A file is
 * new when it is not among the files the owner referenced before the save; it is allowed when the user
 * uploaded it (`files.user_id`) or `MediaPolicy::update` lets the user change it. Files that were already
 * in the node stay valid for everyone who may edit the node. An administrator is unrestricted.
 *
 * It works on `media_relations`, which `ContentValueWriter` keeps in sync for every file-bearing value
 * (fields, YouTube screenshots, rich text) in every language, so it needs no parsing of raw input. Call
 * it inside the transaction that writes the values: a refusal then rolls the whole save back.
 *
 * @example
 *   $before = $guard->snapshot($owner);          // before the write (empty for a node being created)
 *   $writer->write($node, $values);
 *   $guard->assertAllowed($user, $owner, $before); // throws ValidationException keyed by the field
 */
class AttachedFileGuard
{
    public function __construct(private readonly AccessResolver $access) {}

    /**
     * The uuids of the files the owner references now, in every role and language.
     *
     * @return list<string>
     */
    public function snapshot(ValueOwner $owner): array
    {
        return MediaRelation::query()
            ->where('owner_type', $owner->mediaOwnerType)
            ->where('owner_id', $owner->mediaOwnerId)
            ->distinct()
            ->pluck('file_uuid')
            ->all();
    }

    /**
     * Require every file that is new since `$before` to be one the user may attach.
     *
     * A uuid with no row in `files` is skipped: it is not a library file, and the value rules judge it.
     *
     * @param  list<string>  $before  The result of {@see self::snapshot()} taken before the write.
     *
     * @throws ValidationException Keyed by the role (field key) of the refused file.
     */
    public function assertAllowed(EcmsUser $user, ValueOwner $owner, array $before): void
    {
        if ($this->access->isAdministrator($user)) {
            return;
        }

        $new = array_values(array_diff($this->snapshot($owner), $before));

        if ($new === []) {
            return;
        }

        $refused = File::query()
            ->whereIn('uuid', $new)
            ->get()
            ->reject(fn (File $file) => $file->user_id === $user->id || Gate::forUser($user)->allows('update', $file))
            ->pluck('uuid')
            ->all();

        if ($refused === []) {
            return;
        }

        $roles = MediaRelation::query()
            ->where('owner_type', $owner->mediaOwnerType)
            ->where('owner_id', $owner->mediaOwnerId)
            ->whereIn('file_uuid', $refused)
            ->distinct()
            ->pluck('role');

        throw ValidationException::withMessages(
            $roles->mapWithKeys(fn (string $role) => [$role => [__('You cannot attach this file.')]])->all(),
        );
    }
}
