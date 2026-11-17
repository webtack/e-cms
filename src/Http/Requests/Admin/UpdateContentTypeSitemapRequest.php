<?php

namespace Webtack\Ecms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Webtack\Ecms\Models\ContentType;

/**
 * Validates the "Sitemap" tab of a type: `settings.sitemap` with the groups `xml` (`type`, `nodes`, `changefreq`,
 * `priority`) and `web` (`type`, `nodes`). A `page` type has no list page, so `type` is rejected for it. Which keys
 * are stored (nothing for an empty form, `changefreq` / `priority` only with an XML checkbox) is decided by
 * `ContentTypeSitemapController`; `ContentType` repeats the shape check as the last line of defence.
 *
 * @example
 *   // PUT /admin/content-types/news/sitemap
 *   //   settings[sitemap][xml][nodes]=1&settings[sitemap][xml][changefreq]=monthly&settings[sitemap][xml][priority]=0.8
 */
class UpdateContentTypeSitemapRequest extends FormRequest
{
    /** Allowed `priority` values: `0.0` … `1.0` in steps of 0.1. */
    public const PRIORITIES = ['0.0', '0.1', '0.2', '0.3', '0.4', '0.5', '0.6', '0.7', '0.8', '0.9', '1.0'];

    public function authorize(): bool
    {
        return true;
    }

    /** A numeric priority (`1`, `0.8` sent as a number) is brought to the one-digit form (`1.0`, `0.8`). */
    protected function prepareForValidation(): void
    {
        $priority = $this->input('settings.sitemap.xml.priority');

        if (is_int($priority) || is_float($priority)) {
            $this->merge(['settings' => array_replace_recursive($this->input('settings', []), [
                'sitemap' => ['xml' => ['priority' => number_format($priority, 1, '.', '')]],
            ])]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $listPage = $this->route()->defaults['kind'] === 'content' ? ['nullable', 'boolean'] : ['prohibited'];

        return [
            'settings' => ['nullable', 'array:sitemap'],
            'settings.sitemap' => ['nullable', 'array:xml,web'],
            'settings.sitemap.xml' => ['nullable', 'array:type,nodes,changefreq,priority'],
            'settings.sitemap.xml.type' => $listPage,
            'settings.sitemap.xml.nodes' => ['nullable', 'boolean'],
            'settings.sitemap.xml.changefreq' => ['nullable', Rule::in(ContentType::SITEMAP_CHANGEFREQ)],
            'settings.sitemap.xml.priority' => ['nullable', 'string', Rule::in(self::PRIORITIES)],
            'settings.sitemap.web' => ['nullable', 'array:type,nodes'],
            'settings.sitemap.web.type' => $listPage,
            'settings.sitemap.web.nodes' => ['nullable', 'boolean'],
        ];
    }
}
