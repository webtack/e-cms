<?php

namespace Webtack\Ecms\Tests\Unit\Files;

use InvalidArgumentException;
use Webtack\Ecms\Files\FileStorage;
use Webtack\Ecms\Files\UploadRules;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Tests\TestCase;

class UploadRulesTest extends TestCase
{
    private function field(string $type, ?array $settings): ContentField
    {
        return new ContentField(['type' => $type, 'settings' => $settings]);
    }

    public function test_without_settings_the_whole_profile_applies(): void
    {
        $rules = UploadRules::forField($this->field('image', null));

        $this->assertSame('image', $rules->profile);
        $this->assertEqualsCanonicalizing(array_keys(config('files.profiles.image.mimes')), $rules->mimes);
        $this->assertSame(config('files.profiles.image.max_size'), $rules->maxSize);
        $this->assertEquals($rules, UploadRules::forProfile('image'));
    }

    public function test_the_file_type_uses_the_document_profile(): void
    {
        $this->assertSame('document', UploadRules::forField($this->field('file', []))->profile);
    }

    public function test_settings_narrow_the_types_and_the_size(): void
    {
        $rules = UploadRules::forField($this->field('image', ['mimes' => ['image/png'], 'max_size' => 1024]));

        $this->assertSame(['image/png'], $rules->mimes);
        $this->assertSame(1024, $rules->maxSize);
        $this->assertTrue($rules->allows('image/png'));
        $this->assertFalse($rules->allows('image/jpeg'));
    }

    public function test_a_type_outside_the_profile_is_ignored(): void
    {
        $rules = UploadRules::forField($this->field('image', ['mimes' => ['image/png', 'application/pdf']]));

        $this->assertSame(['image/png'], $rules->mimes);
        $this->assertSame([], UploadRules::forField($this->field('image', ['mimes' => ['application/pdf']]))->mimes);
    }

    public function test_a_size_above_the_profile_is_ignored(): void
    {
        $rules = UploadRules::forField($this->field('image', ['max_size' => 999_999]));

        $this->assertSame(config('files.profiles.image.max_size'), $rules->maxSize);
    }

    public function test_damaged_settings_never_widen_the_profile(): void
    {
        foreach ([['mimes' => 'image/png', 'max_size' => 'big'], ['mimes' => [1, null, ['x']], 'max_size' => -5], ['mimes' => [], 'max_size' => 0]] as $settings) {
            $rules = UploadRules::forField($this->field('image', $settings));

            $this->assertSame(config('files.profiles.image.max_size'), $rules->maxSize);
            $this->assertEmpty(array_diff($rules->mimes, array_keys(config('files.profiles.image.mimes'))));
        }
    }

    public function test_executables_and_svg_are_never_allowed_even_in_a_widened_profile(): void
    {
        config(['files.profiles.image.mimes' => ['image/png' => 'png', 'image/svg+xml' => 'svg', 'text/x-php' => 'php']]);

        $this->assertSame(['image/png'], UploadRules::forProfile('image')->mimes);
        $this->assertSame(['image/png'], UploadRules::forField($this->field('image', ['mimes' => ['image/svg+xml', 'image/png']]))->mimes);
        $this->assertContains('image/svg+xml', FileStorage::FORBIDDEN_MIMES);
    }

    public function test_a_field_of_a_type_without_files_is_an_error(): void
    {
        $this->expectException(InvalidArgumentException::class);

        UploadRules::forField($this->field('string', null));
    }

    public function test_without_any_limit_the_sides_are_unrestricted(): void
    {
        config(['files.profiles.image.max_width' => null, 'files.profiles.image.max_height' => null]);

        $rules = UploadRules::forField($this->field('image', null));

        $this->assertNull($rules->maxWidth);
        $this->assertNull($rules->maxHeight);
    }

    public function test_the_profile_limits_apply_to_the_profile_and_to_a_field_without_settings(): void
    {
        config(['files.profiles.image.max_width' => 1200, 'files.profiles.image.max_height' => 800]);

        foreach ([UploadRules::forProfile('image'), UploadRules::forField($this->field('image', null))] as $rules) {
            $this->assertSame(1200, $rules->maxWidth);
            $this->assertSame(800, $rules->maxHeight);
        }
    }

    public function test_environment_strings_are_read_as_numbers(): void
    {
        config(['files.profiles.image.max_width' => '1500', 'files.profiles.image.max_height' => '']);

        $rules = UploadRules::forProfile('image');

        $this->assertSame(1500, $rules->maxWidth);
        $this->assertNull($rules->maxHeight);
    }

    public function test_a_field_narrows_the_pixel_limits(): void
    {
        config(['files.profiles.image.max_width' => 1920, 'files.profiles.image.max_height' => 1920]);

        $rules = UploadRules::forField($this->field('image', ['max_width' => 500, 'max_height' => 600]));

        $this->assertSame(500, $rules->maxWidth);
        $this->assertSame(600, $rules->maxHeight);
    }

    public function test_a_field_never_widens_the_pixel_limits(): void
    {
        config(['files.profiles.image.max_width' => 1920, 'files.profiles.image.max_height' => 1000]);

        $rules = UploadRules::forField($this->field('image', ['max_width' => 4000, 'max_height' => 4000]));

        $this->assertSame(1920, $rules->maxWidth);
        $this->assertSame(1000, $rules->maxHeight);
    }

    public function test_a_field_limit_applies_when_the_profile_has_none(): void
    {
        config(['files.profiles.image.max_width' => null, 'files.profiles.image.max_height' => null]);

        $rules = UploadRules::forField($this->field('image', ['max_width' => 600]));

        $this->assertSame(600, $rules->maxWidth);
        $this->assertNull($rules->maxHeight);
    }

    public function test_malformed_pixel_limits_are_ignored(): void
    {
        config(['files.profiles.image.max_width' => 1920, 'files.profiles.image.max_height' => 1920]);

        foreach ([0, -5, 'wide', [], null, ''] as $value) {
            $rules = UploadRules::forField($this->field('image', ['max_width' => $value, 'max_height' => $value]));

            $this->assertSame(1920, $rules->maxWidth);
            $this->assertSame(1920, $rules->maxHeight);
        }
    }
}
