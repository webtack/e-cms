<?php

namespace Webtack\Ecms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the translation form of the site settings (`PUT /admin/settings/translations/{lang}`): the
 * translatable texts, the schedule and the labels of the existing contact and social rows. A row carries
 * the identity of a stored item (`value`; `network` and `url`), which is only looked up, never changed, so
 * it is not validated beyond its length. Limits come from {@see UpdateSiteSettingsRequest::LIMITS}. Access is
 * decided by the `can:settings` route middleware.
 *
 * @example
 *   // PUT /admin/settings/translations/en  name=My site&phones[0][value]=+380 44 000&phones[0][label]=Sales
 */
class SaveSiteSettingsTranslationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Drop schedule rows whose four columns are all blank, like the base form.
     */
    protected function prepareForValidation(): void
    {
        $schedule = $this->input('schedule');

        if (! is_array($schedule)) {
            return;
        }

        $this->merge(['schedule' => array_values(array_filter(
            $schedule,
            fn ($row) => ! is_array($row) || array_filter($row, fn ($cell) => is_string($cell) ? trim($cell) !== '' : $cell !== null) !== [],
        ))]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $limits = UpdateSiteSettingsRequest::LIMITS;
        $label = ['nullable', 'string', 'max:'.$limits['text']];

        return [
            'name' => ['nullable', 'string', 'max:'.$limits['name']],
            'short_name' => ['nullable', 'string', 'max:'.$limits['short_name']],
            'description' => ['nullable', 'string', 'max:'.$limits['description']],
            'address' => ['nullable', 'string', 'max:'.$limits['address']],

            'emails' => ['nullable', 'array', 'max:'.$limits['list']],
            'emails.*.value' => ['required', 'string', 'max:'.$limits['text']],
            'emails.*.label' => $label,

            'phones' => ['nullable', 'array', 'max:'.$limits['list']],
            'phones.*.value' => ['required', 'string', 'max:'.$limits['text']],
            'phones.*.label' => $label,

            'socials' => ['nullable', 'array', 'max:'.$limits['list']],
            'socials.*.network' => ['required', 'string', 'max:'.$limits['text']],
            'socials.*.url' => ['required', 'string', 'max:'.$limits['url']],
            'socials.*.label' => $label,

            'schedule' => ['nullable', 'array', 'max:'.$limits['list']],
            'schedule.*' => ['array', 'size:4'],
            'schedule.*.*' => ['nullable', 'string', 'max:'.$limits['text']],
        ];
    }
}
