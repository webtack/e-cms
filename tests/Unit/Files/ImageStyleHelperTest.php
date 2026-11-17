<?php

namespace Webtack\Ecms\Tests\Unit\Files;

use Illuminate\Support\Facades\Blade;
use Webtack\Ecms\Files\ImageStyles;
use Webtack\Ecms\Tests\TestCase;

class ImageStyleHelperTest extends TestCase
{
    private const UUID = '0190a1b27c3e7f0a8b1c2d3e4f5a6b7c';

    public function test_the_helper_is_global(): void
    {
        $this->assertTrue(function_exists('image_style'));
    }

    public function test_a_preset_address(): void
    {
        $this->assertSame('/storage/styles/thumb/'.self::UUID.'.webp', image_style('/storage/'.self::UUID.'.jpg', 'thumb'));
    }

    public function test_an_arbitrary_size_address_is_signed(): void
    {
        $file = self::UUID.'.webp';

        $this->assertSame("/storage/styles/300x250/{$file}?itok=".ImageStyles::signature('300x250', $file), image_style('/storage/'.self::UUID.'.jpg', '300x250'));
    }

    public function test_a_value_is_turned_into_an_address(): void
    {
        $this->assertStringEndsWith('/storage/styles/thumb/'.self::UUID.'.webp', image_style(self::UUID.'.png', 'thumb'));
    }

    public function test_an_absolute_address_keeps_scheme_and_host(): void
    {
        $this->assertSame('http://localhost:8080/storage/styles/thumb/'.self::UUID.'.webp', image_style('http://localhost:8080/storage/'.self::UUID.'.jpg', 'thumb'));
    }

    public function test_a_gif_stays_a_gif(): void
    {
        $this->assertSame('/storage/styles/thumb/'.self::UUID.'.gif', image_style('/storage/'.self::UUID.'.gif', 'thumb'));
    }

    public function test_an_external_address_and_an_invalid_style_are_returned_unchanged(): void
    {
        $this->assertSame('https://cdn.example.com/photo.jpg', image_style('https://cdn.example.com/photo.jpg', 'thumb'));
        $this->assertSame('/storage/'.self::UUID.'.jpg', image_style('/storage/'.self::UUID.'.jpg', '300х250'));
    }

    public function test_null_stays_null(): void
    {
        $this->assertNull(image_style(null, 'thumb'));
    }

    public function test_the_helper_works_in_a_blade_template(): void
    {
        $html = Blade::render('<img src="{{ image_style($url, \'thumb\') }}">', ['url' => '/storage/'.self::UUID.'.jpg']);

        $this->assertSame('<img src="/storage/styles/thumb/'.self::UUID.'.webp">', $html);
    }
}
