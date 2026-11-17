<?php

namespace Webtack\Ecms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Webtack\Ecms\Files\Rules\FileValue;
use Webtack\Ecms\Models\ContentField;

/**
 * Validates the site settings form (`PUT /admin/settings`): the values of the `default` language.
 *
 * The bounds are product-neutral defaults kept here in {@see self::LIMITS}; the page receives them as
 * the `limits` prop. Schedule rows with four blank columns are dropped before validation, so they
 * never produce an error. Access is decided by the `can:settings` route middleware.
 *
 * @example
 *   // PUT /admin/settings  name=My site&phones[0][value]=+380 44 000&socials[0][network]=instagram
 *   //   &socials[0][url]=https://instagram.com/x&schedule[0][]=Mon&schedule[0][]=9&schedule[0][]=18&schedule[0][]=
 */
class UpdateSiteSettingsRequest extends FormRequest
{
    /** Bounds of the form; `list` is the most rows of an email, phone, social or schedule list. */
    public const LIMITS = [
        'name' => 255,
        'short_name' => 100,
        'description' => 2000,
        'address' => 500,
        'list' => 20,
        'text' => 255,
        'url' => 2048,
        'phone_pattern' => '/^\+?[0-9\s()\-]{3,32}$/',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Drop schedule rows whose four columns are all blank.
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
        $limits = self::LIMITS;
        $image = fn () => ['nullable', 'string', 'max:255', new FileValue('image', new ContentField(['type' => 'image']))];

        return [
            'name' => ['nullable', 'string', 'max:'.$limits['name']],
            'short_name' => ['nullable', 'string', 'max:'.$limits['short_name']],
            'description' => ['nullable', 'string', 'max:'.$limits['description']],
            'address' => ['nullable', 'string', 'max:'.$limits['address']],
            'logo_large' => $image(),
            'logo_small' => $image(),

            'emails' => ['nullable', 'array', 'max:'.$limits['list']],
            'emails.*.value' => ['required', 'string', 'email', 'max:'.$limits['text']],
            'emails.*.label' => ['nullable', 'string', 'max:'.$limits['text']],

            'phones' => ['nullable', 'array', 'max:'.$limits['list']],
            'phones.*.value' => ['required', 'string', 'regex:'.$limits['phone_pattern']],
            'phones.*.label' => ['nullable', 'string', 'max:'.$limits['text']],

            'socials' => ['nullable', 'array', 'max:'.$limits['list']],
            'socials.*.network' => ['required', 'string', Rule::in(array_keys(config('social_networks')))],
            'socials.*.url' => ['required', 'string', 'url:http,https', 'max:'.$limits['url']],
            'socials.*.label' => ['nullable', 'string', 'max:'.$limits['text']],

            'schedule' => ['nullable', 'array', 'max:'.$limits['list']],
            'schedule.*' => ['array', 'size:4'],
            'schedule.*.*' => ['nullable', 'string', 'max:'.$limits['text']],

            'indexable' => ['boolean'],
        ];
    }
}
