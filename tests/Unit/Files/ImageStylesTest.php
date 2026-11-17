<?php

namespace Webtack\Ecms\Tests\Unit\Files;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Webtack\Ecms\Files\ImageStyles;
use Webtack\Ecms\Tests\TestCase;

class ImageStylesTest extends TestCase
{
    private const UUID = '0190a1b27c3e7f0a8b1c2d3e4f5a6b7c';

    // --- parse ---

    public function test_a_preset_is_resolved_from_the_configuration(): void
    {
        config(['files.styles.presets.thumb' => ['width' => 120, 'height' => 90, 'upload' => true]]);

        $this->assertSame(
            ['name' => 'thumb', 'width' => 120, 'height' => 90, 'preset' => true, 'upload' => true],
            ImageStyles::parse('thumb'),
        );
    }

    /**
     * @return array<string, array{string, int|null, int|null, string}>
     */
    public static function sizes(): array
    {
        return [
            'both' => ['300x250', 300, 250, '300x250'],
            'width only' => ['300x', 300, null, '300x'],
            'height only' => ['x300', null, 300, 'x300'],
            'capital X' => ['300X250', 300, 250, '300x250'],
            'capital X width only' => ['300X', 300, null, '300x'],
            'leading zero' => ['0300x250', 300, 250, '300x250'],
            'at the limit' => ['4096x4096', 4096, 4096, '4096x4096'],
        ];
    }

    #[DataProvider('sizes')]
    public function test_an_arbitrary_size_is_normalized(string $style, ?int $width, ?int $height, string $name): void
    {
        $parsed = ImageStyles::parse($style);

        $this->assertSame([$name, $width, $height, false, false], [$parsed['name'], $parsed['width'], $parsed['height'], $parsed['preset'], $parsed['upload']]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalid(): array
    {
        return [
            'zeros' => ['0x0'],
            'zero width' => ['0x250'],
            'cyrillic x' => ['300х250'],
            'unknown name' => ['abc'],
            'no x' => ['300'],
            'only x' => ['x'],
            'over the limit' => ['4097x100'],
            'huge' => ['99999x99999'],
            'six digits' => ['100000x1'],
            'empty' => [''],
            'path' => ['../300x250'],
            'two x' => ['300x250x3'],
            'negative' => ['-5x10'],
        ];
    }

    #[DataProvider('invalid')]
    public function test_an_invalid_style_is_rejected(string $style): void
    {
        $this->assertNull(ImageStyles::parse($style));
    }

    public function test_the_limit_comes_from_the_configuration(): void
    {
        config(['files.styles.max_dimension' => 500]);

        $this->assertNotNull(ImageStyles::parse('500x500'));
        $this->assertNull(ImageStyles::parse('501x500'));
    }

    public function test_a_preset_named_like_a_size_is_a_configuration_error(): void
    {
        config(['files.styles.presets' => ['300x250' => ['width' => 1, 'height' => 1]]]);

        $this->expectException(InvalidArgumentException::class);
        ImageStyles::parse('thumb');
    }

    public function test_upload_presets_are_listed(): void
    {
        config(['files.styles.presets' => [
            'thumb' => ['width' => 10, 'height' => 10, 'upload' => true],
            'big' => ['width' => 900, 'height' => 900],
        ]]);

        $this->assertSame(['thumb'], array_column(ImageStyles::uploadPresets(), 'name'));
    }

    // --- extension and format ---

    public function test_the_extension_follows_the_format(): void
    {
        config(['files.styles.format' => 'webp']);
        $this->assertSame('webp', ImageStyles::extension('jpg'));
        $this->assertSame('webp', ImageStyles::extension('png'));
        $this->assertSame('webp', ImageStyles::extension('webp'));
        $this->assertSame('gif', ImageStyles::extension('gif'));

        config(['files.styles.format' => 'original']);
        $this->assertSame('jpg', ImageStyles::extension('jpg'));
        $this->assertSame('gif', ImageStyles::extension('gif'));
    }

    public function test_an_unknown_format_is_a_configuration_error(): void
    {
        config(['files.styles.format' => 'avif']);

        $this->expectException(InvalidArgumentException::class);
        ImageStyles::extension('jpg');
    }

    // --- signature and path ---

    public function test_the_signature_is_bound_to_style_and_file(): void
    {
        $signature = ImageStyles::signature('300x250', self::UUID.'.webp');

        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $signature);
        $this->assertSame($signature, ImageStyles::signature('300x250', self::UUID.'.webp'));
        $this->assertNotSame($signature, ImageStyles::signature('400x400', self::UUID.'.webp'));
        $this->assertNotSame($signature, ImageStyles::signature('300x250', self::UUID.'.gif'));
    }

    public function test_the_signature_depends_on_the_application_key(): void
    {
        $before = ImageStyles::signature('300x250', self::UUID.'.webp');
        config(['app.key' => 'base64:'.base64_encode(str_repeat('k', 32))]);

        $this->assertNotSame($before, ImageStyles::signature('300x250', self::UUID.'.webp'));
    }

    public function test_the_disk_path(): void
    {
        $this->assertSame('styles/thumb/'.self::UUID.'.webp', ImageStyles::path('thumb', self::UUID.'.webp'));
    }

    // --- url ---

    public function test_a_preset_url_is_not_signed(): void
    {
        $this->assertSame('/storage/styles/thumb/'.self::UUID.'.webp', ImageStyles::url('/storage/'.self::UUID.'.jpg', 'thumb'));
    }

    public function test_an_arbitrary_size_url_is_signed(): void
    {
        $file = self::UUID.'.webp';

        $this->assertSame(
            "/storage/styles/300x250/{$file}?itok=".ImageStyles::signature('300x250', $file),
            ImageStyles::url('/storage/'.self::UUID.'.jpg', '300X250'),
        );
    }

    public function test_the_signature_uses_the_extension_of_the_style_file(): void
    {
        config(['files.styles.format' => 'original']);

        $this->assertSame(
            '/storage/styles/x300/'.self::UUID.'.jpg?itok='.ImageStyles::signature('x300', self::UUID.'.jpg'),
            ImageStyles::url('/storage/'.self::UUID.'.jpg', 'x300'),
        );
    }

    public function test_an_absolute_url_keeps_scheme_and_host(): void
    {
        $this->assertSame(
            'http://localhost:8080/storage/styles/thumb/'.self::UUID.'.webp',
            ImageStyles::url('http://localhost:8080/storage/'.self::UUID.'.jpg', 'thumb'),
        );
    }

    public function test_a_query_of_the_original_is_dropped(): void
    {
        $this->assertSame('/storage/styles/thumb/'.self::UUID.'.webp', ImageStyles::url('/storage/'.self::UUID.'.jpg?v=3', 'thumb'));
    }

    public function test_a_value_becomes_the_url_of_the_style_on_the_image_disk(): void
    {
        $this->assertSame(
            rtrim(config('filesystems.disks.public.url'), '/').'/styles/thumb/'.self::UUID.'.webp',
            ImageStyles::url(self::UUID.'.png', 'thumb'),
        );
    }

    public function test_a_gif_keeps_its_extension(): void
    {
        $this->assertSame('/storage/styles/thumb/'.self::UUID.'.gif', ImageStyles::url('/storage/'.self::UUID.'.gif', 'thumb'));
    }

    public function test_an_external_address_is_returned_unchanged(): void
    {
        $this->assertSame('https://cdn.example.com/photo.jpg', ImageStyles::url('https://cdn.example.com/photo.jpg', 'thumb'));
    }

    public function test_a_document_address_is_returned_unchanged(): void
    {
        $this->assertSame('/storage/'.self::UUID.'.pdf', ImageStyles::url('/storage/'.self::UUID.'.pdf', 'thumb'));
    }

    public function test_an_invalid_style_returns_the_input(): void
    {
        $this->assertSame('/storage/'.self::UUID.'.jpg', ImageStyles::url('/storage/'.self::UUID.'.jpg', '300х250'));
    }

    public function test_an_empty_value_gives_null(): void
    {
        $this->assertNull(ImageStyles::url(null, 'thumb'));
        $this->assertNull(ImageStyles::url('', 'thumb'));
    }
}
