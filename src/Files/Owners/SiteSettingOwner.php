<?php

namespace Webtack\Ecms\Files\Owners;

/**
 * Owner type `site_setting`: a file used as a logo on the site settings page. The settings are a single
 * entity without a database row, so the only owner id is {@see self::ID} (`0`); any other id is unknown.
 * The label is fixed and the link leads to the settings page.
 *
 * @example
 *   (new SiteSettingOwner)->describe([0]);
 *   // [0 => ['label' => 'Налаштування сайту', 'url' => '/admin/settings']]
 *   (new SiteSettingOwner)->describe([7]); // []
 */
class SiteSettingOwner implements MediaOwner
{
    /** The owner id of the site settings (not a real row id: those start from 1). */
    public const ID = 0;

    public const TYPE = 'site_setting';

    public function describe(array $ids): array
    {
        if (! in_array(self::ID, $ids, true)) {
            return [];
        }

        return [self::ID => ['label' => 'Налаштування сайту', 'url' => '/admin/settings']];
    }
}
