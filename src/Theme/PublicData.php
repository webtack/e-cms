<?php

namespace Webtack\Ecms\Theme;

use Webtack\Ecms\Content\LocalizedUrl;
use Webtack\Ecms\Files\FileStorage;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Settings\SiteSettings;

/**
 * The variables every public template receives: `$site` (the SiteSettings in the reading language, with the fallback to the default texts), `$lang` (the code of the document language, for `<html lang>`), `$homeUrl` (the address of the home page in the reading language: `/` or `/en`), `$languages` (the language switcher data), `$alternates` and `$canonical` (the `hreflang` and canonical links for `<head>`, see {@see LanguageLinks}), `$theme` (active theme name),
 * `$title` (document title), `$logoUrl` (small logo URL or null), `$meta` (document metadata) and `$assets`
 * (Vite entries of the theme) and `$assetTags` (their HTML tags), `$breadcrumbs` (the trail: a list of `title`/`url`, empty on the home page and 404; `url` is `null` on the current page) and `$breadcrumbsJsonLd` (its `BreadcrumbList` JSON or `null`; both come from {@see Breadcrumbs} after the `breadcrumbs` preprocess hook), plus whatever the route adds (`$node`, `$type`, `$paginator`, `$values`, `$fields`).
 *
 * The title is the `$meta['title']` of the chain as it is (no site name is appended; a theme adds a suffix with
 * a hook or in its layout), else the record title, else the site name alone. `$meta` always has the keys
 * `title`, `description` and `image` (an absolute-path URL), each `null` when no source has a value; it is built by
 * {@see PageMeta} in the order record → fields chosen by the type → (title) record title → `meta_*` of the type → site settings, from `$extra['values']` and `$extra['type']`.
 *
 * @example
 *   $data = app(PublicData::class)->make(['node' => $node, 'values' => $values], $values['title'] ?? null);
 *   $data['title']; // "About us"
 */
class PublicData
{
    public function __construct(
        private readonly SiteSettings $site,
        private readonly ThemeManager $themes,
        private readonly FileStorage $files,
        private readonly PageMeta $pageMeta,
        private readonly Breadcrumbs $breadcrumbs,
        private readonly LanguageLinks $languageLinks,
    ) {}

    /**
     * @param  array<string, mixed>  $extra  Route-specific variables.
     * @param  string|null  $recordTitle  Title of the shown record, if any.
     * @return array<string, mixed>
     */
    public function make(array $extra = [], ?string $recordTitle = null): array
    {
        $url = app(LocalizedUrl::class);
        // The settings in the reading language, each missing text replaced by the default one.
        $site = $this->site->forLang($url->lang());
        $siteName = $site->name() ?? (string) config('app.name');
        $logo = $site->logoSmall();
        $meta = $this->pageMeta->make(
            is_array($extra['values'] ?? null) ? $extra['values'] : [],
            ($extra['type'] ?? null) instanceof ContentType ? $extra['type'] : null,
        );
        $recordTitle = $meta['title'] ?? $recordTitle;

        $trail = $this->themes->hooks()->applyPreprocess('breadcrumbs', [
            'breadcrumbs' => $this->breadcrumbs->for(
                ($extra['type'] ?? null) instanceof ContentType ? $extra['type'] : null,
                ($extra['node'] ?? null) instanceof ContentNode ? $extra['node'] : null,
                $recordTitle,
            ),
        ])['breadcrumbs'] ?? [];
        $trail = array_values($trail);

        $links = $this->languageLinks->make(($extra['node'] ?? null) instanceof ContentNode ? $extra['node'] : null);

        return [
            'site' => $site,
            'lang' => $url->code(),
            'homeUrl' => $url->path('/'),
            'languages' => $links['languages'],
            'alternates' => $links['alternates'],
            'canonical' => $links['canonical'],
            'theme' => $this->themes->active(),
            'title' => $recordTitle !== null && $recordTitle !== '' ? $recordTitle : $siteName,
            'logoUrl' => $logo !== null ? $this->files->urlForValue('image', $logo) : null,
            'meta' => $meta,
            'assets' => $this->themes->assets(),
            'assetTags' => $this->themes->assetTags(),
            'breadcrumbs' => $trail,
            'breadcrumbsJsonLd' => $this->breadcrumbs->jsonLd($trail),
            ...$extra,
        ];
    }
}
