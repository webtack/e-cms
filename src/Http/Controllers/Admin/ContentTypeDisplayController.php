<?php

namespace Webtack\Ecms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Content\ContentValueWriter;
use Webtack\Ecms\Content\PublicDisplaySettings;
use Webtack\Ecms\Content\ValueOwner;
use Webtack\Ecms\Http\Controllers\Controller;
use Webtack\Ecms\Http\Requests\Admin\UpdateContentTypeDisplayRequest;
use Webtack\Ecms\Models\ContentType;

/**
 * Saves the "Display" tab of a type of any kind in one request: the `section_*` values of the type itself (kind
 * `content` only) and `settings.public`.
 *
 * Both parts are written in one transaction, so an error in either one saves nothing. Value errors come back keyed
 * like the form (`values.section_title`). Other keys of `settings` (such as `list`) are never touched, and neither
 * is the meta data choice `settings.public.meta`, which the "SEO" tab saves ({@see ContentTypeValuesController::update()}).
 *
 * @example
 *   // PUT /admin/content-types/news/display  values[section_title]=News&settings[public][per_page]=10
 */
class ContentTypeDisplayController extends Controller
{
    public function __construct(private readonly ContentValueWriter $writer) {}

    public function update(UpdateContentTypeDisplayRequest $request, ContentType $contentType): RedirectResponse
    {
        DB::transaction(function () use ($request, $contentType) {
            // Types of other kinds have no section values; the key is ignored for them.
            if ($contentType->kind === 'content') {
                try {
                    $this->writer->write(ValueOwner::forType($contentType), $request->input('values', []));
                } catch (ValidationException $e) {
                    throw ValidationException::withMessages(
                        collect($e->errors())->mapWithKeys(fn (array $messages, string $key) => ["values.$key" => $messages])->all(),
                    );
                }
            }

            if ($request->has('settings.public')) {
                $contentType->update([
                    'settings' => self::mergePublicSettings($contentType->settings, $request->validated('settings.public'), $contentType->kind),
                ]);
            }
        });

        return back()->with('success', __('Display saved.'));
    }

    /**
     * The type's `settings` with `public` (display settings) replaced by the submitted ones, so other keys survive.
     * Defaults are not stored: empty `list_fields`, the sort "publication date, descending", `per_page = 0` and
     * `pagination = true` are dropped, `archive` is kept only while its `field` is chosen (a cleared choice removes the key), and a missing `page_fields` ("all added fields") stays missing. The stored `meta` is kept as it is
     * (the "SEO" tab owns it). A kind without a list (`page`, `block`) keeps whatever list keys were submitted, so that the model rejects them.
     *
     * @param  array<string, mixed>|null  $settings  The stored settings.
     * @param  array<string, mixed>|null  $public  The submitted `settings.public`.
     * @return array<string, mixed>|null
     *
     * @example
     *   self::mergePublicSettings(['list' => ['search' => ['title']]], ['page_fields' => ['price'], 'per_page' => 0], 'content');
     *   // ['list' => ['search' => ['title']], 'public' => ['page_fields' => ['price']]]
     */
    private static function mergePublicSettings(?array $settings, ?array $public, string $kind): ?array
    {
        $public ??= [];
        $clean = [];

        if (array_key_exists('page_fields', $public) && $public['page_fields'] !== null) {
            $clean['page_fields'] = array_values($public['page_fields']);
        }

        $listFields = array_values($public['list_fields'] ?? []);
        $sort = $public['sort'] ?? null;
        $perPage = $public['per_page'] ?? null;
        $pagination = array_key_exists('pagination', $public) && $public['pagination'] !== null ? (bool) $public['pagination'] : null;
        $strict = $kind !== 'content';

        if ($listFields !== [] || ($strict && array_key_exists('list_fields', $public))) {
            $clean['list_fields'] = $listFields;
        }

        if (is_array($sort) && ($sort['field'] ?? null) !== null) {
            $direction = $sort['direction'] ?? 'desc';

            if ($strict || ! ($sort['field'] === PublicDisplaySettings::DEFAULT_SORT['field'] && $direction === PublicDisplaySettings::DEFAULT_SORT['direction'])) {
                $clean['sort'] = ['field' => $sort['field'], 'direction' => $direction];
            }
        }

        if ($perPage !== null && ($strict || (int) $perPage !== 0)) {
            $clean['per_page'] = (int) $perPage;
        }

        if ($pagination !== null && ($strict || $pagination === false)) {
            $clean['pagination'] = $pagination;
        }

        $archive = $public['archive'] ?? null;

        if (is_array($archive) && ($archive['field'] ?? null) !== null) {
            $clean['archive'] = ['field' => $archive['field']];

            if (is_array($archive['sort'] ?? null) && ($archive['sort']['field'] ?? null) !== null) {
                $clean['archive']['sort'] = ['field' => $archive['sort']['field'], 'direction' => $archive['sort']['direction'] ?? 'desc'];
            }
        } elseif ($strict && is_array($archive)) {
            // A kind without a list keeps whatever was submitted, so that the model rejects it.
            $clean['archive'] = $archive;
        }

        // The meta data choice is not edited here; keep the stored one.
        if (isset($settings['public']['meta'])) {
            $clean['meta'] = $settings['public']['meta'];
        }

        return ContentTypeController::replaceSetting($settings, 'public', $clean);
    }
}
