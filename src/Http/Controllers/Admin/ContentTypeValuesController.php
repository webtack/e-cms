<?php

namespace Webtack\Ecms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Content\ContentValueWriter;
use Webtack\Ecms\Content\ValueOwner;
use Webtack\Ecms\Http\Controllers\Controller;
use Webtack\Ecms\Http\Requests\Admin\UpdateContentTypeValuesRequest;
use Webtack\Ecms\Models\ContentType;

/**
 * Saves the "SEO" tab of a content or page type in one request: the `meta_*` values of the type itself and, when the
 * request carries it, the "Meta data" choice `settings.public.meta`.
 *
 * Only the base language is edited here. An empty value clears the stored one. Both parts are written in one
 * transaction, so an error in either one saves nothing. Value errors come back keyed like the form
 * (`values.meta_title`), those of the choice under `settings`. Only `settings.public.meta` is merged: the other keys
 * of `public` and of `settings` survive, an empty choice is dropped and an empty `meta` is not stored.
 *
 * @example
 *   // PUT /admin/content-types/news/values  values[meta_title]=News&settings[public][meta][title]=title
 */
class ContentTypeValuesController extends Controller
{
    public function __construct(private readonly ContentValueWriter $writer) {}

    public function update(UpdateContentTypeValuesRequest $request, ContentType $contentType): RedirectResponse
    {
        DB::transaction(function () use ($request, $contentType) {
            try {
                $this->writer->write(ValueOwner::forType($contentType), $request->input('values', []));
            } catch (ValidationException $e) {
                throw ValidationException::withMessages(
                    collect($e->errors())->mapWithKeys(fn (array $messages, string $key) => ["values.$key" => $messages])->all(),
                );
            }

            if ($request->has('settings.public.meta')) {
                $settings = $contentType->settings;
                $public = $settings['public'] ?? [];
                unset($public['meta']);

                $meta = array_filter($request->validated('settings.public.meta') ?? [], fn ($key) => is_string($key) && $key !== '');

                if ($meta !== []) {
                    $public['meta'] = $meta;
                }

                // The model validates the chosen fields and fails with an error on `settings`.
                $contentType->update(['settings' => ContentTypeController::replaceSetting($settings, 'public', $public)]);
            }
        });

        return back()->with('success', __('Values saved.'));
    }
}
