<?php

namespace Webtack\Ecms\Http\Controllers\Admin\Concerns;

use Illuminate\Http\Request;

/**
 * Tells a type controller which section it serves: the content-type kind comes from the matched
 * route's `kind` default (see `routes/web.php`), the rest is derived from it.
 *
 * @example
 *   // GET /admin/page-types/home/fields
 *   $this->kind($request);     // 'page'
 *   $this->routeName($request, 'fields.index'); // 'admin.page-types.fields.index'
 *   $this->section($request);  // ['kind' => 'page', 'basePath' => '/admin/page-types']
 */
trait ResolvesTypeSection
{
    /** The kind of the current section (`content`, `page` or `block`). */
    protected function kind(Request $request): string
    {
        return $request->route()->defaults['kind'];
    }

    /** The URL segment of the section, e.g. `block-types` for kind `block`. */
    protected function segment(Request $request): string
    {
        return TypeSections::segment($this->kind($request));
    }

    /** Full name of a route of the current section, e.g. `index` → `admin.page-types.index`. */
    protected function routeName(Request $request, string $name): string
    {
        return 'admin.'.$this->segment($request).'.'.$name;
    }

    /**
     * Inertia prop describing the section for the shared pages and components.
     *
     * @return array{kind: string, basePath: string}
     */
    protected function section(Request $request): array
    {
        return ['kind' => $this->kind($request), 'basePath' => '/admin/'.$this->segment($request)];
    }
}
