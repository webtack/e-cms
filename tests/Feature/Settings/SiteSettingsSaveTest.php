<?php

namespace Webtack\Ecms\Tests\Feature\Settings;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Webtack\Ecms\Files\FileRelations;
use Webtack\Ecms\Models\File;
use Webtack\Ecms\Settings\SiteSettings;
use Webtack\Ecms\Support\AppSettings;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class SiteSettingsSaveTest extends TestCase
{
    use RefreshDatabase;

    private function site(): SiteSettings
    {
        return app(SiteSettings::class);
    }

    private function stored(string $key): mixed
    {
        $row = DB::table('app_settings')->where('key', "site.{$key}")->value('value');

        return $row === null ? null : json_decode($row, true);
    }

    private function image(): File
    {
        $uuid = File::newUuid();

        return File::factory()->create(['uuid' => $uuid, 'profile' => 'image', 'mime' => 'image/png', 'path' => "{$uuid}.png", 'size' => 1000]);
    }

    public function test_values_are_stored_under_default(): void
    {
        $this->site()->save([
            'name' => 'My site',
            'short_name' => 'Site',
            'description' => "Line one\nLine two",
            'address' => "1 Main St\nKyiv",
            'emails' => [['value' => 'a@b.c', 'label' => 'General']],
            'socials' => [['network' => 'facebook', 'url' => 'https://f.example', 'label' => '']],
            'schedule' => [['Mon', '9', '18', '']],
            'indexable' => true,
        ]);

        $this->assertSame(['default' => 'My site'], $this->stored('name'));
        $this->assertSame(['default' => 'Site'], $this->stored('short_name'));
        $this->assertSame(['default' => "Line one\nLine two"], $this->stored('description'));
        $this->assertSame(['default' => "1 Main St\nKyiv"], $this->stored('address'));
        $this->assertSame([['value' => 'a@b.c', 'label' => ['default' => 'General']]], $this->stored('emails'));
        $this->assertSame([['network' => 'facebook', 'url' => 'https://f.example']], $this->stored('socials'));
        $this->assertSame(['default' => [['Mon', '9', '18', '']]], $this->stored('schedule'));
        $this->assertTrue($this->stored('indexable'));
    }

    public function test_a_blank_name_is_forgotten_and_others_stay(): void
    {
        $this->site()->save(['name' => 'A', 'short_name' => 'B']);
        $this->site()->save(['name' => '  ', 'short_name' => 'B']);

        $this->assertNull($this->stored('name'));
        $this->assertSame(['default' => 'B'], $this->stored('short_name'));
        $this->assertNull($this->site()->name());
    }

    public function test_a_blank_label_is_a_missing_key(): void
    {
        $this->site()->save(['emails' => [['value' => 'a@b.c', 'label' => 'X']]]);
        $this->site()->save(['emails' => [['value' => 'a@b.c', 'label' => '']]]);

        $this->assertSame([['value' => 'a@b.c']], $this->stored('emails'));
    }

    public function test_empty_schedule_rows_are_dropped_and_empty_lists_forgotten(): void
    {
        $this->site()->save(['schedule' => [['', ' ', '', ''], ['Sat', '', '', '']], 'phones' => []]);

        $this->assertSame(['default' => [['Sat', '', '', '']]], $this->stored('schedule'));
        $this->assertNull($this->stored('phones'));

        $this->site()->save(['schedule' => [['', '', '', '']]]);
        $this->assertNull($this->stored('schedule'));
    }

    public function test_other_prefixes_are_untouched(): void
    {
        app(AppSettings::class)->set('content.multilingual', true);

        $this->site()->save(['name' => 'A']);

        $this->assertTrue(app(AppSettings::class)->get('content.multilingual'));
        $this->assertSame(1, DB::table('app_settings')->where('key', 'content.multilingual')->count());
    }

    public function test_other_languages_are_kept(): void
    {
        app(AppSettings::class)->set('site.name', ['default' => 'Old', 'en' => 'English']);
        app(AppSettings::class)->set('site.emails', [['value' => 'a@b.c', 'label' => ['default' => 'Old', 'en' => 'Mail']]]);
        app(AppSettings::class)->set('site.schedule', ['default' => [['a', '', '', '']], 'en' => [['b', '', '', '']]]);

        $this->site()->save([
            'name' => 'New',
            'emails' => [['value' => 'x@y.z', 'label' => 'Other'], ['value' => 'a@b.c', 'label' => 'New label']],
            'schedule' => [['c', '', '', '']],
        ]);

        $this->assertSame(['default' => 'New', 'en' => 'English'], $this->stored('name'));
        $this->assertSame([
            ['value' => 'x@y.z', 'label' => ['default' => 'Other']],
            ['value' => 'a@b.c', 'label' => ['default' => 'New label', 'en' => 'Mail']],
        ], $this->stored('emails'));
        $this->assertSame(['default' => [['c', '', '', '']], 'en' => [['b', '', '', '']]], $this->stored('schedule'));
    }

    public function test_saving_unchanged_values_changes_nothing(): void
    {
        $data = ['name' => 'A', 'phones' => [['value' => '+380 1', 'label' => 'S']], 'indexable' => false];
        $this->site()->save($data);
        $before = DB::table('app_settings')->orderBy('key')->get(['key', 'value'])->all();

        $this->site()->save($data);

        $this->assertEquals($before, DB::table('app_settings')->orderBy('key')->get(['key', 'value'])->all());
    }

    public function test_logos_are_bound_replaced_and_cleared(): void
    {
        $first = $this->image();
        $second = $this->image();
        $relations = app(FileRelations::class);

        $this->site()->save(['logo_large' => $first->path]);
        $this->assertSame(['default' => $first->path], $this->stored('logo_large'));
        $this->assertSame([$first->uuid => 1], $relations->usageCounts([$first->uuid]));
        $this->assertDatabaseHas('media_relations', ['file_uuid' => $first->uuid, 'owner_type' => 'site_setting', 'owner_id' => 0, 'role' => 'logo_large', 'lang' => 'default']);

        $this->site()->save(['logo_large' => $second->path, 'logo_small' => $first->path]);
        $this->assertSame([$first->uuid => 1, $second->uuid => 1], $relations->usageCounts([$first->uuid, $second->uuid]));
        $this->assertDatabaseMissing('media_relations', ['file_uuid' => $first->uuid, 'role' => 'logo_large']);

        $this->site()->save(['logo_large' => null, 'logo_small' => '']);
        $this->assertSame([$first->uuid => 0, $second->uuid => 0], $relations->usageCounts([$first->uuid, $second->uuid]));
        $this->assertNull($this->stored('logo_large'));
        $this->assertNotNull(File::findByUuid($first->uuid));
    }

    public function test_a_used_logo_cannot_be_deleted_from_the_media_library(): void
    {
        $logo = $this->image();
        $this->site()->save(['logo_large' => $logo->path]);

        Storage::fake('public');

        $this->actingAs(User::factory()->create())->delete("/admin/media/{$logo->uuid}")->assertSessionHas('error');
        $this->assertNotNull(File::findByUuid($logo->uuid));
    }

    public function test_a_failure_rolls_back_values_relations_and_cache(): void
    {
        $logo = $this->image();
        $this->site()->save(['name' => 'Before']);

        try {
            // Invalid UTF-8 cannot be JSON-encoded, so the write throws after the name was already set.
            $this->site()->save(['name' => 'After', 'logo_large' => $logo->path, 'emails' => [['value' => 'a@b.c', 'label' => "\xB1\x31"]]]);
            $this->fail('The save should have failed.');
        } catch (\Throwable) {
        }

        $this->assertSame(['default' => 'Before'], $this->stored('name'));
        $this->assertSame([$logo->uuid => 0], app(FileRelations::class)->usageCounts([$logo->uuid]));
        $this->assertSame('Before', $this->site()->name());
    }
}
