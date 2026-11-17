<?php

namespace Webtack\Ecms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Webtack\Ecms\Access\AccessResolver;
use Webtack\Ecms\Http\Controllers\Controller;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\UserFavoriteType;

/**
 * Adds a type to, or removes it from, the personal "Favorites" list of the signed-in user (the star on the node list).
 *
 * Open to every active user, but only for a type whose node level for the user is not `none` (403 otherwise,
 * also for a key that does not exist; an existing-level key of a missing type answers 404). Both actions are
 * idempotent and answer with a redirect back, so the Inertia page keeps its table state. See docs/admin-favorites.md.
 *
 * @example
 *   // POST   /admin/favorites/news   → news is in the list
 *   // DELETE /admin/favorites/news   → news is not in the list
 */
class FavoriteTypeController extends Controller
{
    public function __construct(private readonly AccessResolver $access) {}

    /** Puts the type into the user's favorites (a repeated call changes nothing). */
    public function store(Request $request, string $typeKey): RedirectResponse
    {
        $this->authorizeType($request, $typeKey);

        UserFavoriteType::query()->firstOrCreate(['user_id' => $request->user()->id, 'type_key' => $typeKey]);

        return back();
    }

    /** Takes the type out of the user's favorites (a missing row is not an error). */
    public function destroy(Request $request, string $typeKey): RedirectResponse
    {
        $this->authorizeType($request, $typeKey);

        UserFavoriteType::query()->where('user_id', $request->user()->id)->where('type_key', $typeKey)->delete();

        return back();
    }

    /** 403 without access to the type's nodes, 404 for a missing type. */
    private function authorizeType(Request $request, string $typeKey): void
    {
        abort_if($this->access->nodeLevel($request->user(), $typeKey) === AccessResolver::NONE, 403);

        ContentType::query()->where('key', $typeKey)->firstOrFail();
    }
}
