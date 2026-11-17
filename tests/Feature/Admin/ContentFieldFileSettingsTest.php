<?php

namespace Webtack\Ecms\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Content\ContentNodeCreator;
use Webtack\Ecms\Content\ContentValueReader;
use Webtack\Ecms\Content\ContentValueWriter;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\File;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class ContentFieldFileSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        ContentType::create(['key' => 'news', 'name' => 'News']);
    }

    private function admin(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return ['key' => 'cover', 'name' => 'Cover', 'type' => 'image', 'is_multiple' => false, 'is_required' => false, 'is_translatable' => false, 'weight' => 10, ...$overrides];
    }

    private function cover(): ContentField
    {
        return ContentField::query()->where('content_type', 'news')->where('key', 'cover')->firstOrFail();
    }

    public function test_the_forms_get_the_profile_rules_of_the_file_types(): void
    {
        $expected = [
            'image' => ['mimes' => array_keys(config('files.profiles.image.mimes')), 'labels' => config('files.profiles.image.mimes'), 'max_size' => 30 * 1024, 'max_width' => 1920, 'max_height' => 1920],
            'file' => ['mimes' => array_keys(config('files.profiles.document.mimes')), 'labels' => config('files.profiles.document.mimes'), 'max_size' => 30 * 1024, 'max_width' => null, 'max_height' => null],
        ];

        $this->admin()->get('/admin/content-types/news/fields/create')
            ->assertInertia(fn (Assert $page) => $page->where('fileTypes', $expected));

        ContentField::create(['content_type' => 'news', 'key' => 'cover', 'name' => 'Cover', 'type' => 'image']);
        $this->admin()->get('/admin/content-types/news/fields/cover/edit')
            ->assertInertia(fn (Assert $page) => $page->where('fileTypes', $expected)->where('field.settings', null));
    }

    public function test_settings_are_saved_and_shown_on_edit(): void
    {
        $this->admin()->post('/admin/content-types/news/fields', $this->payload(['settings' => ['mimes' => ['image/png'], 'max_size' => 1024]]))
            ->assertRedirect('/admin/content-types/news/fields');

        $this->assertSame(['mimes' => ['image/png'], 'max_size' => 1024], $this->cover()->settings);
        $this->admin()->get('/admin/content-types/news/fields/cover/edit')
            ->assertInertia(fn (Assert $page) => $page->where('field.settings', ['mimes' => ['image/png'], 'max_size' => 1024]));
    }

    public function test_a_field_without_settings_gets_none(): void
    {
        $this->admin()->post('/admin/content-types/news/fields', $this->payload(['type' => 'file']))->assertRedirect();
        $this->assertNull($this->cover()->settings);

        $this->admin()->put('/admin/content-types/news/fields/cover', $this->payload(['type' => 'file', 'settings' => ['mimes' => [], 'max_size' => null]]))->assertRedirect();
        $this->assertNull($this->cover()->settings);
    }

    public function test_a_type_outside_the_profile_is_rejected(): void
    {
        $this->admin()->post('/admin/content-types/news/fields', $this->payload(['settings' => ['mimes' => ['application/pdf']]]))
            ->assertSessionHasErrors('settings.mimes.0');

        $this->assertSame(0, ContentField::query()->where('key', 'cover')->count());
    }

    public function test_executables_and_svg_are_rejected_even_when_the_profile_lists_them(): void
    {
        config(['files.profiles.image.mimes' => ['image/png' => 'png', 'image/svg+xml' => 'svg']]);

        $this->admin()->post('/admin/content-types/news/fields', $this->payload(['settings' => ['mimes' => ['image/svg+xml']]]))
            ->assertSessionHasErrors('settings.mimes.0');
    }

    public function test_a_size_above_the_profile_limit_is_rejected(): void
    {
        $this->admin()->post('/admin/content-types/news/fields', $this->payload(['settings' => ['max_size' => 30 * 1024 + 1]]))
            ->assertSessionHasErrors('settings.max_size');
        $this->admin()->post('/admin/content-types/news/fields', $this->payload(['settings' => ['max_size' => 0]]))
            ->assertSessionHasErrors('settings.max_size');

        $this->assertSame(0, ContentField::query()->where('key', 'cover')->count());
    }

    public function test_other_types_take_no_settings(): void
    {
        $this->admin()->post('/admin/content-types/news/fields', $this->payload(['type' => 'string', 'settings' => ['max_size' => 10]]))
            ->assertSessionHasErrors('settings');
        $this->admin()->post('/admin/content-types/news/fields', $this->payload(['type' => 'string']))->assertRedirect();
    }

    public function test_narrowing_a_field_that_has_values_is_allowed_and_keeps_them(): void
    {
        $this->admin()->post('/admin/content-types/news/fields', $this->payload())->assertRedirect();
        $uuid = File::newUuid();
        File::factory()->create(['uuid' => $uuid, 'profile' => 'image', 'mime' => 'image/jpeg', 'path' => "{$uuid}.jpg"]);
        $node = app(ContentNodeCreator::class)->create(ContentType::findByKey('news'), ['title' => 'A'], authorId: $this->authorId());
        app(ContentValueWriter::class)->write($node, ['cover' => "{$uuid}.jpg"]);

        $this->admin()->put('/admin/content-types/news/fields/cover', $this->payload(['settings' => ['mimes' => ['image/png']]]))->assertRedirect();

        $this->assertSame(['mimes' => ['image/png']], $this->cover()->settings);
        $this->assertSame("{$uuid}.jpg", app(ContentValueReader::class)->load([$node], ['cover'])[$node->id]['cover']);
    }

    // --- Pixel limits ---

    public function test_the_pixel_limits_are_saved_and_shown_on_edit(): void
    {
        $settings = ['max_width' => 800, 'max_height' => 600];

        $this->admin()->post('/admin/content-types/news/fields', $this->payload(['settings' => $settings]))
            ->assertRedirect('/admin/content-types/news/fields');

        $this->assertSame($settings, $this->cover()->settings);
        $this->admin()->get('/admin/content-types/news/fields/cover/edit')
            ->assertInertia(fn (Assert $page) => $page->where('field.settings', $settings));
    }

    public function test_the_pixel_limits_are_saved_together_with_the_other_settings_and_as_integers(): void
    {
        $this->admin()->post('/admin/content-types/news/fields', $this->payload(['settings' => ['mimes' => ['image/png'], 'max_size' => 1024, 'max_width' => '800']]))
            ->assertRedirect();

        $this->assertSame(['mimes' => ['image/png'], 'max_size' => 1024, 'max_width' => 800], $this->cover()->settings);
    }

    public function test_a_width_above_the_profile_limit_is_rejected(): void
    {
        $this->admin()->post('/admin/content-types/news/fields', $this->payload(['settings' => ['max_width' => 1921]]))
            ->assertSessionHasErrors('settings.max_width');
        $this->admin()->post('/admin/content-types/news/fields', $this->payload(['settings' => ['max_height' => 1921]]))
            ->assertSessionHasErrors('settings.max_height');

        $this->assertSame(0, ContentField::query()->where('key', 'cover')->count());
    }

    public function test_the_profile_limit_itself_is_accepted(): void
    {
        $this->admin()->post('/admin/content-types/news/fields', $this->payload(['settings' => ['max_width' => 1920]]))
            ->assertRedirect();
    }

    public function test_without_a_profile_limit_any_positive_width_is_accepted(): void
    {
        config(['files.profiles.image.max_width' => null]);

        $this->admin()->post('/admin/content-types/news/fields', $this->payload(['settings' => ['max_width' => 10000]]))
            ->assertRedirect();

        $this->assertSame(['max_width' => 10000], $this->cover()->settings);
    }

    public function test_a_zero_negative_or_non_numeric_limit_is_rejected(): void
    {
        foreach ([0, -5, 'wide', 12.5] as $value) {
            $this->admin()->post('/admin/content-types/news/fields', $this->payload(['settings' => ['max_width' => $value]]))
                ->assertSessionHasErrors('settings.max_width');
        }

        $this->assertSame(0, ContentField::query()->where('key', 'cover')->count());
    }

    public function test_a_file_field_takes_no_pixel_limits(): void
    {
        $this->admin()->post('/admin/content-types/news/fields', $this->payload(['type' => 'file', 'settings' => ['max_width' => 500]]))
            ->assertSessionHasErrors('settings');

        $this->assertSame(0, ContentField::query()->where('key', 'cover')->count());
    }

    public function test_empty_pixel_limits_leave_no_settings(): void
    {
        $this->admin()->post('/admin/content-types/news/fields', $this->payload(['settings' => ['mimes' => [], 'max_size' => null, 'max_width' => null, 'max_height' => '']]))
            ->assertRedirect();

        $this->assertNull($this->cover()->settings);
    }

    public function test_changing_the_limits_of_a_field_with_values_keeps_the_values(): void
    {
        $this->admin()->post('/admin/content-types/news/fields', $this->payload(['settings' => ['max_width' => 1000]]))->assertRedirect();
        $uuid = File::newUuid();
        File::factory()->create(['uuid' => $uuid, 'profile' => 'image', 'mime' => 'image/jpeg', 'path' => "{$uuid}.jpg"]);
        $node = app(ContentNodeCreator::class)->create(ContentType::findByKey('news'), ['title' => 'A'], authorId: $this->authorId());
        app(ContentValueWriter::class)->write($node, ['cover' => "{$uuid}.jpg"]);

        $this->admin()->put('/admin/content-types/news/fields/cover', $this->payload(['settings' => ['max_width' => 300]]))->assertRedirect();

        $this->assertSame(['max_width' => 300], $this->cover()->settings);
        $this->assertSame("{$uuid}.jpg", app(ContentValueReader::class)->load([$node], ['cover'])[$node->id]['cover']);
    }
}
