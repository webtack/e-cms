<?php

namespace Webtack\Ecms\Content;

use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Rules\TypeSlugHierarchy;

/**
 * Hierarchy rules of the public slugs of `content` types.
 *
 * A type slug has one or more segments separated by `/` (`common/institutions`). A type that has a parent
 * and a slug must have a slug that starts with the parent's slug, then `/`, then a non-empty tail; the
 * parent must have a slug. The slug of a type cannot change while a direct child has a slug. Comparisons
 * are case-insensitive and by whole segments. Both the model (last barrier) and the form rule
 * ({@see TypeSlugHierarchy}) call this service, so they give the same messages.
 *
 * @example
 *   $slugs = app(TypeSlugs::class);
 *   $slugs->startsWithParent('common/institutions', 'common'); // true
 *   $slugs->startsWithParent('commonplace/x', 'common');       // false: not a whole segment
 *   $slugs->hierarchyError('institutions', 'common');          // "The slug must start with "common/"."
 *   $slugs->childrenBlockSlugChange($type);                    // message while a child has a slug, else null
 */
class TypeSlugs
{
    /**
     * Whether `$slug` is `$parentSlug`, `/`, and a non-empty tail (case-insensitive, whole segments).
     *
     * @example
     *   $slugs->startsWithParent('common/city/awards', 'common'); // true
     *   $slugs->startsWithParent('common', 'common');             // false: the tail is empty
     *   $slugs->startsWithParent('common/', 'common');            // false
     */
    public function startsWithParent(string $slug, string $parentSlug): bool
    {
        $prefix = strtolower($parentSlug).'/';

        return strlen($slug) > strlen($prefix) && str_starts_with(strtolower($slug), $prefix);
    }

    /**
     * Why the slug breaks the hierarchy for the given parent type, or null when it is fine.
     * No slug or no parent never breaks it.
     *
     * @param  string|null  $slug  The slug being saved.
     * @param  string|null  $parentKey  Key of the parent type.
     *
     * @example
     *   $slugs->hierarchyError('common/institutions', 'common'); // null when `common` has the slug "common"
     *   $slugs->hierarchyError('institutions', 'common');        // 'The slug must start with "common/".'
     *   $slugs->hierarchyError('a/b', 'no-slug-parent');         // 'The parent type must have a slug first.'
     */
    public function hierarchyError(?string $slug, ?string $parentKey): ?string
    {
        if ($slug === null || $slug === '' || $parentKey === null || $parentKey === '') {
            return null;
        }

        $parentSlug = ContentType::findByKey($parentKey)?->slug;

        if ($parentSlug === null || $parentSlug === '') {
            return __('The parent type must have a slug first.');
        }

        return $this->startsWithParent($slug, $parentSlug)
            ? null
            : __('The slug must start with ":prefix".', ['prefix' => $parentSlug.'/']);
    }

    /**
     * The prefix a child slug has to start with, or null when the parent has no slug.
     *
     * @example $slugs->requiredPrefix('common'); // "common/"
     */
    public function requiredPrefix(string $parentKey): ?string
    {
        $parentSlug = ContentType::findByKey($parentKey)?->slug;

        return $parentSlug === null || $parentSlug === '' ? null : $parentSlug.'/';
    }

    /**
     * Whether a direct child of the type has a slug.
     */
    public function hasChildWithSlug(ContentType $type): bool
    {
        return ContentType::query()
            ->where('parent_type', $type->key)
            ->whereNotNull('slug')
            ->exists();
    }

    /**
     * The message that blocks changing or clearing the slug of `$type`, or null when it may change.
     * Only direct children matter: a grandchild with a slug needs a child with a slug.
     *
     * @example
     *   $slugs->childrenBlockSlugChange($common); // 'Change the slugs of the child types first.' if a child has a slug
     */
    public function childrenBlockSlugChange(ContentType $type): ?string
    {
        return $this->hasChildWithSlug($type) ? __('Change the slugs of the child types first.') : null;
    }
}
