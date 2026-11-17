<?php

namespace Webtack\Ecms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Content\SystemFields;
use Webtack\Ecms\Content\TranslationSaver;
use Webtack\Ecms\Content\ValueOwner;
use Webtack\Ecms\Http\Controllers\Admin\Concerns\ResolvesTranslationLanguage;
use Webtack\Ecms\Http\Controllers\Admin\Concerns\ResolvesTypeSection;
use Webtack\Ecms\Http\Controllers\Controller;
use Webtack\Ecms\Http\Requests\Admin\SaveTypeValuesTranslationRequest;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentType;

/**
 * Saves and clears one language of the values of the type itself (the translation mode of the "Display" and "SEO"
 * tabs, see `docs/admin-translation-mode.md`).
 *
 * A tab is named by its `panel` (`section` = "Display", `seo` = "SEO"): it decides which fields may be sent and which
 * are cleared, so one tab never changes the translations of the other. A type that holds no values in the panel
 * (every `block`, a `page` on "Display") answers 404. The right is the type configuration right (route middleware
 * `can:types`). Value errors come back keyed like the form (`values.section_title`). `file_meta` carries the translated
 * descriptions of the files the tab's fields hold (the section cover, the meta image); a file of another tab or of no
 * value of the type is an error under `file_meta.<uuid>.description`.
 *
 * @example
 *   // PUT    /admin/content-types/news/values/translations/en  panel=section&values[section_title]=News
 *   // DELETE /admin/content-types/news/values/translations/en  panel=seo
 */
class ContentTypeValuesTranslationController extends Controller
{
    use ResolvesTranslationLanguage, ResolvesTypeSection;

    public function __construct(private readonly TranslationSaver $translations, private readonly SystemFields $systemFields) {}

    public function save(SaveTypeValuesTranslationRequest $request, ContentType $contentType, string $lang): RedirectResponse
    {
        $lang = $this->activeTranslationLang($lang) ?? abort(404);
        $panel = $request->validated('panel');
        $keys = $this->panelKeys($contentType, $panel);
        $values = $request->input('values', []);

        // A key of the other tab (or an unknown one) is an error of that key, nothing is stored.
        $foreign = array_diff(array_keys($values), $keys);
        if ($foreign !== []) {
            throw ValidationException::withMessages(collect($foreign)->mapWithKeys(fn ($key) => ["values.$key" => ["The field [{$key}] is not edited on this tab."]])->all());
        }

        try {
            DB::transaction(function () use ($contentType, $lang, $values, $keys, $request) {
                $owner = ValueOwner::forType($contentType);

                $this->translations->save($owner, $lang, $values);
                $this->translations->saveFileDescriptions($owner, $lang, $request->validated('file_meta') ?? [], $keys);
            });
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(
                collect($e->errors())->mapWithKeys(fn (array $messages, string $key) => [str_starts_with($key, 'file_meta.') ? $key : "values.$key" => $messages])->all(),
            );
        }

        return $this->back($request, $contentType, $panel, $lang)->with('success', __('Translation saved.'));
    }

    public function clear(Request $request, ContentType $contentType, string $lang): RedirectResponse
    {
        $lang = $this->activeTranslationLang($lang) ?? abort(404);
        $panel = $request->validate((new SaveTypeValuesTranslationRequest)->rules())['panel'];

        DB::transaction(fn () => $this->translations->clear(ValueOwner::forType($contentType), $lang, $this->panelKeys($contentType, $panel)));

        return $this->back($request, $contentType, $panel, $lang)->with('success', __('Translation cleared.'));
    }

    /**
     * Keys of the fields the type holds on a tab; 404 when there are none (nothing to translate there).
     *
     * @return list<string>
     */
    private function panelKeys(ContentType $type, string $panel): array
    {
        $keys = ValueOwner::forType($type)->fields($type->fields->keyBy('key'))
            ->filter(fn (ContentField $field) => $this->systemFields->panel($field->key) === $panel)
            ->keys()->all();

        abort_if($keys === [], 404);

        return $keys;
    }

    /** The same tab in the same language: "Display" is `…/display`, "SEO" is `…/seo`. */
    private function back(Request $request, ContentType $type, string $panel, string $lang): RedirectResponse
    {
        return to_route($this->routeName($request, $panel === 'seo' ? 'seo' : 'display'), [$type->key, 'lang' => $lang]);
    }
}
