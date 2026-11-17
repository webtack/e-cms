<?php

namespace Webtack\Ecms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Webtack\Ecms\Content\ContentLanguages;
use Webtack\Ecms\Files\FileStorage;
use Webtack\Ecms\Http\Controllers\Admin\Concerns\ResolvesTranslationLanguage;
use Webtack\Ecms\Http\Controllers\Controller;
use Webtack\Ecms\Http\Requests\Admin\SaveSiteSettingsTranslationRequest;
use Webtack\Ecms\Http\Requests\Admin\UpdateSiteSettingsRequest;
use Webtack\Ecms\Models\File;
use Webtack\Ecms\Settings\SiteSettings;

/**
 * The site settings page (`/admin/settings`): one form for the whole `site.*` set, saved in one go.
 *
 * Reads and writes go through {@see SiteSettings}. The base form shows and saves the `default` language only;
 * translations are edited one language at a time (`?lang=` on the same page, see `docs/admin-translation-mode.md`)
 * by `saveTranslation` and `clearTranslation`, which change only that language. Every route sits behind the
 * `can:settings` middleware (see `routes/web.php`).
 *
 * @example
 *   // GET /admin/settings
 *   // GET /admin/settings?lang=en
 *   // PUT /admin/settings  name=My site&indexable=1
 *   // PUT /admin/settings/translations/en  name=My site
 */
class SiteSettingsController extends Controller
{
    use ResolvesTranslationLanguage;

    public function __construct(
        private readonly SiteSettings $site,
        private readonly FileStorage $files,
    ) {}

    /**
     * The form with the stored values, logo previews, the social network list and the list bounds. With `?lang=`
     * of an active additional language it is the translation mode: `values` stay the base values (shown inert
     * for non-translatable controls) and `translation.values` carries the prefilled translatable ones.
     */
    public function edit(Request $request): Response
    {
        $lang = $this->resolveTranslationLang($request);
        $values = $this->site->formValues();

        return Inertia::render('Admin/Settings/Site', [
            'values' => $values,
            'logoFiles' => [
                'logo_large' => $this->fileInfo($values['logo_large']),
                'logo_small' => $this->fileInfo($values['logo_small']),
            ],
            'networkOptions' => $this->networkOptions($values['socials']),
            'limits' => [
                'name' => UpdateSiteSettingsRequest::LIMITS['name'],
                'short_name' => UpdateSiteSettingsRequest::LIMITS['short_name'],
                'description' => UpdateSiteSettingsRequest::LIMITS['description'],
                'address' => UpdateSiteSettingsRequest::LIMITS['address'],
                'list' => UpdateSiteSettingsRequest::LIMITS['list'],
                'text' => UpdateSiteSettingsRequest::LIMITS['text'],
            ],
            'translation' => $this->translationProp(
                $lang,
                $this->site->translatedLanguages(app(ContentLanguages::class)->active()),
                $lang === null ? null : $this->site->translationValues($lang),
                $lang !== null && $this->site->hasTranslation($lang),
            ),
        ]);
    }

    /**
     * Saves the translatable settings of one language (the translation mode). Values equal to the base are not
     * stored and an empty value removes its translation; the base, other languages, logos and lists are unchanged.
     */
    public function saveTranslation(SaveSiteSettingsTranslationRequest $request, string $lang): RedirectResponse
    {
        $lang = $this->activeTranslationLang($lang) ?? abort(404);

        $this->site->saveTranslation($lang, $request->validated());

        return to_route('admin.settings.edit', ['lang' => $lang])->with('success', __('Translation saved.'));
    }

    /** Removes every translated setting of one language (the "Clear translation" action). */
    public function clearTranslation(string $lang): RedirectResponse
    {
        $lang = $this->activeTranslationLang($lang) ?? abort(404);

        $this->site->clearTranslation($lang);

        return to_route('admin.settings.edit', ['lang' => $lang])->with('success', __('Translation cleared.'));
    }

    /** Saves the whole set and returns to the form. */
    public function update(UpdateSiteSettingsRequest $request): RedirectResponse
    {
        $this->site->save($request->validated());

        return back()->with('success', __('Settings saved.'));
    }

    /**
     * The networks of the reference book as select options; a stored network that is no longer in it is
     * appended so that editing does not lose the record.
     *
     * @param  list<array{network: string}>  $socials
     * @return list<array{value: string, label: string}>
     */
    private function networkOptions(array $socials): array
    {
        $options = [];

        foreach (config('social_networks') as $key => $network) {
            $options[] = ['value' => $key, 'label' => $network['name']];
        }

        foreach ($socials as $social) {
            if (! array_key_exists($social['network'], config('social_networks')) && ! in_array($social['network'], array_column($options, 'value'), true)) {
                $options[] = ['value' => $social['network'], 'label' => $social['network']];
            }
        }

        return $options;
    }

    /**
     * The description of a stored logo for the upload widget; null when there is none.
     *
     * @return array{uuid: string, name: string, description: ?string, mime: string, size: int, url: string, preview: string|null}|null
     */
    private function fileInfo(?string $value): ?array
    {
        if ($value === null) {
            return null;
        }

        $file = File::query()->where('uuid', substr($value, 0, 32))->first();

        return [
            'uuid' => substr($value, 0, 32),
            'name' => $file?->original_name ?? $value,
            'description' => $file?->description,
            'mime' => $file?->mime ?? '',
            'size' => $file?->size ?? 0,
            'url' => $this->files->urlForValue('image', $value),
            'preview' => $this->files->previewForValue('image', $value, $file?->mime ?? ''),
        ];
    }
}
