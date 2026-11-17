<?php

namespace Webtack\Ecms\Tests\Feature\Settings;

use Webtack\Ecms\Files\Owners\SiteSettingOwner;
use Webtack\Ecms\Tests\TestCase;

class SiteSettingOwnerTest extends TestCase
{
    public function test_it_describes_the_settings_owner(): void
    {
        $this->assertSame(
            [0 => ['label' => 'Налаштування сайту', 'url' => '/admin/settings']],
            (new SiteSettingOwner)->describe([0]),
        );
    }

    public function test_other_ids_are_unknown(): void
    {
        $this->assertSame([], (new SiteSettingOwner)->describe([7]));
        $this->assertSame([], (new SiteSettingOwner)->describe([]));
    }

    public function test_it_is_registered_for_the_media_library(): void
    {
        $this->assertSame(SiteSettingOwner::class, config('files.owners.site_setting'));
    }
}
