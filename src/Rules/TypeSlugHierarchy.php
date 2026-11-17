<?php

namespace Webtack\Ecms\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Webtack\Ecms\Content\TypeSlugs;
use Webtack\Ecms\Models\ContentType;

/**
 * Field-level form of the hierarchy rules of type slugs ({@see TypeSlugs}): the slug must start with the slug
 * of the submitted `parent_type`, the parent must have a slug, and the slug of a type cannot change or be
 * cleared while a direct child has a slug. The model enforces the same rules as the last barrier.
 *
 * Runs on empty values too (`$implicit`), because clearing the slug of a parent can be blocked. As in the
 * model, the hierarchy is checked only when the slug or the parent changes, so an older row that breaks the
 * rule does not block other edits.
 *
 * @example
 *   'slug' => ['nullable', new TypeSlugHierarchy]          // create form
 *   'slug' => ['nullable', new TypeSlugHierarchy($type)]   // edit form of an existing type
 */
class TypeSlugHierarchy implements DataAwareRule, ValidationRule
{
    /** Run for an empty slug as well: clearing the slug of a parent is checked. */
    public bool $implicit = true;

    /** @var array<string, mixed> */
    private array $data = [];

    public function __construct(private readonly ?ContentType $type = null) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $slug = is_string($value) && $value !== '' ? $value : null;
        $parent = is_string($this->data['parent_type'] ?? null) && $this->data['parent_type'] !== ''
            ? $this->data['parent_type']
            : null;
        $slugs = app(TypeSlugs::class);

        $slugChanged = $this->type === null || $slug !== $this->type->slug;
        $parentChanged = $this->type === null || $parent !== $this->type->parent_type;

        if (($slugChanged || $parentChanged) && $message = $slugs->hierarchyError($slug, $parent)) {
            $fail($message);

            return;
        }

        if ($this->type !== null && $slugChanged && $this->type->slug !== null
            && $message = $slugs->childrenBlockSlugChange($this->type)) {
            $fail($message);
        }
    }
}
