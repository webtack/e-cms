<?php

namespace Webtack\Ecms\Policies;

use Webtack\Ecms\Access\AccessResolver;
use Webtack\Ecms\Contracts\EcmsUser;
use Webtack\Ecms\Models\File;

/**
 * Access to the media library by the level of the user's role on `media`.
 *
 * The owner of a file is the user who uploaded it (`media.user_id`); a file without an owner needs `any`.
 *
 * @example
 *   $this->authorize('viewAny', File::class);
 *   $this->authorize('delete', $file);
 */
class MediaPolicy
{
    public function __construct(private readonly AccessResolver $access) {}

    /** The library page and a file's details. */
    public function viewAny(EcmsUser $user): bool
    {
        return $this->access->mediaLevel($user) !== AccessResolver::NONE;
    }

    public function update(EcmsUser $user, File $file): bool
    {
        return $this->owns($user, $file);
    }

    public function delete(EcmsUser $user, File $file): bool
    {
        return $this->owns($user, $file);
    }

    private function owns(EcmsUser $user, File $file): bool
    {
        return match ($this->access->mediaLevel($user)) {
            AccessResolver::ANY => true,
            AccessResolver::OWN => $file->user_id !== null && $file->user_id === $user->id,
            default => false,
        };
    }
}
