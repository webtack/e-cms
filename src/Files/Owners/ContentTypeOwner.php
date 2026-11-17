<?php

namespace Webtack\Ecms\Files\Owners;

use Illuminate\Support\Facades\DB;
use Webtack\Ecms\Http\Controllers\Admin\Concerns\TypeSections;

/**
 * Owner type `content_type`: a file used in a value of the content type itself (`meta_image`,
 * `section_cover`). The label is the name of the type, the link leads to the settings page of the
 * type in the section of its kind (`content-types`, `page-types` or `block-types`).
 *
 * @example
 *   // a type `news` of kind content → ['label' => 'News', 'url' => '/admin/content-types/news/edit']
 */
class ContentTypeOwner implements MediaOwner
{
    public function describe(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $described = [];

        foreach (DB::table('content_types')->whereIn('id', $ids)->get(['id', 'key', 'name', 'kind']) as $type) {
            $described[$type->id] = [
                'label' => $type->name,
                'url' => route('admin.'.TypeSections::segment($type->kind).'.edit', $type->key, false),
            ];
        }

        return $described;
    }
}
