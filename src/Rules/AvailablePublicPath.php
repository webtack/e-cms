<?php

namespace Webtack\Ecms\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Webtack\Ecms\Content\PublicPaths;
use Webtack\Ecms\Models\ContentType;

/**
 * Rejects a slug that collides on the first URL segment: a page slug may not equal a `content` type slug or a
 * reserved path, a `content` type slug may not equal a page slug or a reserved path ({@see PublicPaths}).
 *
 * For a type, the check also covers a node address equal to the slug and the type's own nodes (`$type` is the edited type).
 * `$current` is the slug the record already has: an unchanged slug is skipped, so an existing collision never
 * blocks other edits. The models enforce the same rule as the last barrier.
 *
 * @example
 *   'slug' => ['nullable', new AvailablePublicPath(forPage: true)]                    // a new page node
 *   'slug' => ['nullable', new AvailablePublicPath(forPage: false, current: 'news', type: $type)] // editing a content type
 */
class AvailablePublicPath implements ValidationRule
{
    public function __construct(private readonly bool $forPage, private readonly ?string $current = null, private readonly ?ContentType $type = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '' || ($this->current !== null && strtolower($value) === strtolower($this->current))) {
            return;
        }

        $paths = app(PublicPaths::class);
        $reason = $this->forPage ? $paths->pageSlugConflict($value) : $paths->typeSlugConflict($value, $this->type);

        if ($reason !== null) {
            $fail(PublicPaths::message($reason))->translate();
        }
    }
}
