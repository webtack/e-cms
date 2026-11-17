<?php

namespace Webtack\Ecms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Webtack\Ecms\Http\Controllers\Admin\Concerns\ResolvesTypeSection;
use Webtack\Ecms\Http\Controllers\Controller;
use Webtack\Ecms\Http\Requests\Admin\UpdateContentTypeSitemapRequest;
use Webtack\Ecms\Models\ContentType;

/**
 * The "Sitemap" tab of a `content` or `page` type: shows and saves `settings.sitemap` (see `docs/type-sitemap.md`).
 *
 * Only `settings.sitemap` is written; every other key of `settings` stays. A group keeps only the checkboxes that are
 * on, `changefreq` / `priority` are stored only while an XML checkbox is on (defaults `weekly` / `0.5`), and an
 * empty result removes the key, so a type with nothing enabled looks like one never configured. Routed for kinds
 * `content` and `page` only, so `block` answers 404.
 *
 * @example
 *   // GET /admin/content-types/news/sitemap
 *   // PUT /admin/content-types/news/sitemap  settings[sitemap][xml][nodes]=1&settings[sitemap][web][type]=1
 */
class ContentTypeSitemapController extends Controller
{
    use ResolvesTypeSection;

    /** Defaults shown by the form and stored with an XML checkbox. */
    public const DEFAULT_CHANGEFREQ = 'weekly';

    public const DEFAULT_PRIORITY = '0.5';

    public function edit(Request $request, ContentType $contentType): Response
    {
        $stored = $contentType->settings['sitemap'] ?? [];

        return Inertia::render('Admin/ContentTypes/Sitemap', [
            'type' => $contentType->navProps(),
            'section' => $this->section($request),
            // A `content` type without a slug has no public address: the form shows a hint.
            'hasSlug' => $contentType->slug !== null && $contentType->slug !== '',
            'sitemap' => [
                'xml' => [
                    'type' => (bool) ($stored['xml']['type'] ?? false),
                    'nodes' => (bool) ($stored['xml']['nodes'] ?? false),
                    'changefreq' => $stored['xml']['changefreq'] ?? self::DEFAULT_CHANGEFREQ,
                    'priority' => $stored['xml']['priority'] ?? self::DEFAULT_PRIORITY,
                ],
                'web' => [
                    'type' => (bool) ($stored['web']['type'] ?? false),
                    'nodes' => (bool) ($stored['web']['nodes'] ?? false),
                ],
            ],
            'changefreqOptions' => ContentType::SITEMAP_CHANGEFREQ,
            'priorityOptions' => UpdateContentTypeSitemapRequest::PRIORITIES,
        ]);
    }

    public function update(UpdateContentTypeSitemapRequest $request, ContentType $contentType): RedirectResponse
    {
        $contentType->update([
            'settings' => ContentTypeController::replaceSetting(
                $contentType->settings,
                'sitemap',
                self::clean($request->validated('settings.sitemap') ?? []),
            ),
        ]);

        return back()->with('success', __('Sitemap saved.'));
    }

    /**
     * The submitted groups reduced to what is stored: only the checkboxes that are on, and the XML `changefreq` /
     * `priority` (defaulted) only while an XML checkbox is on.
     *
     * @param  array<string, mixed>  $submitted
     * @return array<string, array<string, mixed>>
     *
     * @example
     *   self::clean(['xml' => ['nodes' => true], 'web' => ['type' => false]]);
     *   // ['xml' => ['nodes' => true, 'changefreq' => 'weekly', 'priority' => '0.5']]
     */
    private static function clean(array $submitted): array
    {
        $clean = [];

        foreach (['xml', 'web'] as $group) {
            $values = array_filter([
                'type' => (bool) ($submitted[$group]['type'] ?? false),
                'nodes' => (bool) ($submitted[$group]['nodes'] ?? false),
            ]);

            if ($values === []) {
                continue;
            }

            if ($group === 'xml') {
                $values['changefreq'] = $submitted['xml']['changefreq'] ?? self::DEFAULT_CHANGEFREQ;
                $values['priority'] = $submitted['xml']['priority'] ?? self::DEFAULT_PRIORITY;
            }

            $clean[$group] = $values;
        }

        return $clean;
    }
}
