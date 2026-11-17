<?php

namespace Webtack\Ecms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Webtack\Ecms\Content\ContentLanguages;
use Webtack\Ecms\Http\Controllers\Controller;
use Webtack\Ecms\Http\Requests\Admin\StoreContentLanguageRequest;
use Webtack\Ecms\Http\Requests\Admin\UpdateContentLanguageRequest;
use Webtack\Ecms\Http\Requests\Admin\UpdateContentLanguageSettingsRequest;
use Webtack\Ecms\Models\AppLanguage;
use Webtack\Ecms\Support\AppSettings;

/**
 * The content languages page (`/admin/settings/languages`): the multilingual switch, the base language
 * code and the list of additional languages. It never touches the admin interface locale.
 *
 * All routes sit behind the `can:settings` middleware (see `routes/web.php`); a language is addressed by
 * its `code`. The rules live in {@see ContentLanguages} and {@see AppLanguage}.
 *
 * @example
 *   // GET    /admin/settings/languages
 *   // PUT    /admin/settings/languages           multilingual=1&base_lang=uk
 *   // POST   /admin/settings/languages           code=en&name=English&is_active=1
 *   // PUT    /admin/settings/languages/en        name=English&is_active=1&weight=2
 *   // DELETE /admin/settings/languages/en
 */
class ContentLanguageController extends Controller
{
    public function __construct(
        private readonly ContentLanguages $languages,
        private readonly AppSettings $settings,
    ) {}

    /** The switch, the saved and effective base code and the languages ordered by weight. */
    public function index(): Response
    {
        return Inertia::render('Admin/Settings/Languages', [
            'multilingual' => $this->languages->isMultilingual(),
            'baseLang' => $this->languages->baseLang(),
            'savedBaseLang' => $this->languages->savedBaseLang(),
            'languages' => AppLanguage::query()->orderBy('weight')->orderBy('id')->get()
                ->map(fn (AppLanguage $language) => [
                    'code' => $language->code,
                    'name' => $language->name,
                    'is_active' => $language->is_active,
                    'weight' => $language->weight,
                ])->all(),
            'limits' => ['code' => AppLanguage::CODE_MAX_LENGTH, 'name' => 255],
        ]);
    }

    /** Saves the base code first, then the switch, in one transaction: a rejected switch keeps the old code. */
    public function updateSettings(UpdateContentLanguageSettingsRequest $request): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request) {
                $code = $request->validated('base_lang');

                if (is_string($code) && $code !== '') {
                    $this->languages->setBaseLang($code);
                }

                $this->languages->setMultilingual($request->boolean('multilingual'));
            });
        } catch (ValidationException $e) {
            // The settings store caches in memory; a rolled back write must not stay readable.
            $this->settings->flush();

            throw $e;
        }

        return back()->with('success', __('Languages saved.'));
    }

    /** Adds an additional language. */
    public function store(StoreContentLanguageRequest $request): RedirectResponse
    {
        AppLanguage::create($request->validated());

        return back()->with('success', __('Language added.'));
    }

    /** Changes the name, activity and weight; the code stays. */
    public function update(UpdateContentLanguageRequest $request, AppLanguage $language): RedirectResponse
    {
        $language->update($request->validated());

        return back()->with('success', __('Language saved.'));
    }

    /** Deletes a language without any stored translation; otherwise explains and suggests deactivating. */
    public function destroy(AppLanguage $language): RedirectResponse
    {
        if ($this->languages->isInUse($language->code)) {
            throw ValidationException::withMessages([
                'language' => __('Translations in this language exist, so it cannot be deleted. Deactivate it instead.'),
            ]);
        }

        $language->delete();

        return back()->with('success', __('Language deleted.'));
    }
}
