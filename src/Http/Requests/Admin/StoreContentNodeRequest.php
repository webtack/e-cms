<?php

namespace Webtack\Ecms\Http\Requests\Admin;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Rules\AvailablePublicPath;

/**
 * Validates the create form of a content node: the optional `slug`, that `values` is an array and the
 * `file_meta` of its files (see {@see self::fileMetaRules()}).
 *
 * The values themselves are not checked here: `ContentValueWriter::validate()` builds their rules from the
 * type's fields and is the single source of them. They live under their own `values` key because a field
 * key may equal `slug`. An empty slug arrives as `null` (the middleware trims empty strings) and the node
 * creator then generates one from the title. `is_active`, `published_at` (`Y-m-d\TH:i`, UTC, empty means now) and
 * the optional `unpublished_at` (same format, must be later than the publish date) are the service attributes; the author is never taken from the request (`user_id` is not a rule, so it never reaches
 * `validated()`). `UpdateContentNodeRequest` reuses {@see self::slugRules()} and {@see self::PUBLISHED_AT_FORMAT}.
 *
 * @example
 *   // POST /admin/content-types/news/nodes  slug=about&values[title]=About
 *   $creator->create($type, $request->input('values', []), $request->validated('slug'));
 */
class StoreContentNodeRequest extends FormRequest
{
    /** Format of `published_at` in forms: minute precision, no time zone (UTC like the datetime fields). */
    public const PUBLISHED_AT_FORMAT = 'Y-m-d\TH:i';

    public function authorize(): bool
    {
        return $this->user()->can('create', [ContentNode::class, $this->route('contentType')]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'slug' => ['nullable', ...self::slugRules(), Rule::unique('content_nodes', 'slug'), ...self::publicPathRules($this->route('contentType'))],
            'is_active' => ['sometimes', 'boolean'],
            'published_at' => ['nullable', 'date_format:'.self::PUBLISHED_AT_FORMAT],
            'unpublished_at' => ['nullable', 'date_format:'.self::PUBLISHED_AT_FORMAT],
            'values' => ['sometimes', 'array'],
            ...self::fileMetaRules(),
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => self::scheduleCheck($validator)];
    }

    /**
     * Checks that `unpublished_at` is later than `published_at` (to the minute), and adds the error under
     * `unpublished_at`. An empty `published_at` means "now", so an end date in the past is rejected too.
     * Skipped when either field already failed its format rule.
     *
     * @example
     *   // published_at=2026-05-01T10:00&unpublished_at=2026-05-01T10:00 -> error on unpublished_at
     */
    public static function scheduleCheck(Validator $validator): void
    {
        $data = $validator->getData();

        if ($validator->errors()->hasAny(['published_at', 'unpublished_at']) || empty($data['unpublished_at'])) {
            return;
        }

        $start = empty($data['published_at'])
            ? now()->startOfMinute()
            : Carbon::createFromFormat(self::PUBLISHED_AT_FORMAT, $data['published_at'], 'UTC')->startOfMinute();
        $end = Carbon::createFromFormat(self::PUBLISHED_AT_FORMAT, $data['unpublished_at'], 'UTC')->startOfMinute();

        if (! $end->greaterThan($start)) {
            $validator->errors()->add('unpublished_at', __('The unpublish date must be later than the publish date.'));
        }
    }

    /**
     * Rules of `file_meta` shared by both forms: the name and description of the files the form shows, keyed
     * by file uuid (32 chars `0-9a-f`). Which files the node really uses is decided by the controller.
     *
     * @return array<string, array<int, mixed>>
     *
     * @example
     *   // file_meta[0190a1b27c3e7f0a8b1c2d3e4f5a6b7c][name]=Cover&file_meta[…][description]=Front cover
     *   self::fileMetaRules()['file_meta.*.name']; // ['required', 'string', 'max:255']
     */
    public static function fileMetaRules(): array
    {
        return [
            'file_meta' => ['sometimes', 'array', function (string $attribute, mixed $value, Closure $fail) {
                foreach (array_keys((array) $value) as $key) {
                    if (! is_string($key) || preg_match('/^[0-9a-f]{32}$/', $key) !== 1) {
                        $fail('The :attribute keys must be file uuids.');
                    }
                }
            }],
            'file_meta.*' => ['array'],
            'file_meta.*.name' => ['required', 'string', 'max:255'],
            'file_meta.*.description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * The first-segment collision rule of a slug, only for page types (their nodes stand on the first URL segment).
     *
     * @param  string|null  $current  Slug the node already has (edit form): left unchecked while unchanged.
     * @return list<AvailablePublicPath>
     *
     * @example
     *   self::publicPathRules($pageType);    // [AvailablePublicPath]
     *   self::publicPathRules($contentType); // []
     */
    public static function publicPathRules(?ContentType $type, ?string $current = null): array
    {
        return $type?->kind === 'page' ? [new AvailablePublicPath(forPage: true, current: $current)] : [];
    }

    /**
     * Format rules of a slug shared by both forms (uniqueness is added by each form).
     *
     * @param  string|null  $storedSlug  The slug the node already has (edit form only).
     * @param  mixed  $submitted  The slug the form sent; when it equals `$storedSlug` the strict format is not required.
     * @return list<string>
     *
     * @example
     *   self::slugRules(); // ['string', 'max:190', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/']
     *   self::slugRules('jam-session-', 'jam-session-'); // ['string', 'max:190'] (an imported legacy slug, unchanged)
     */
    public static function slugRules(?string $storedSlug = null, mixed $submitted = null): array
    {
        // An unchanged slug of an imported node may have the legacy format ({@see ContentNode::LEGACY_SLUG_PATTERN}).
        if ($storedSlug !== null && $submitted === $storedSlug) {
            return ['string', 'max:190'];
        }

        return ['string', 'max:190', 'regex:'.ContentNode::SLUG_PATTERN];
    }
}
