<?php

namespace Webtack\Ecms\Tests\Feature\Menus;

use PHPUnit\Framework\Attributes\DataProvider;
use Webtack\Ecms\Menus\MenuUrl;
use Webtack\Ecms\Tests\TestCase;

class MenuUrlTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function validUrls(): array
    {
        return [
            'relative' => ['/about'],
            'root' => ['/'],
            'anchor' => ['#contacts'],
            'query' => ['?page=2'],
            'http' => ['http://example.com'],
            'https' => ['https://example.com/page?x=1#top'],
            'mailto' => ['mailto:info@example.com'],
            'tel' => ['tel:+380441234567'],
            'upper scheme' => ['HTTPS://example.com'],
            'encoded backslash' => ['/%5Cx'],
        ];
    }

    /** @return array<string, array{string}> */
    public static function invalidUrls(): array
    {
        return [
            'javascript' => ['javascript:alert(1)'],
            'data' => ['data:text/html,x'],
            'about' => ['about:blank'],
            'protocol relative' => ['//example.com'],
            'no slash' => ['about'],
            'backslash after slash' => ['/\evil.example'],
            'backslash inside' => ['/a\b'],
            'space' => ['/about us'],
            'newline' => ["/about\n"],
            'control char' => ["/a\x01b"],
            'empty' => [''],
            'http without host' => ['http://'],
            'empty mailto' => ['mailto:'],
            'too long' => ['/'.str_repeat('a', 2048)],
        ];
    }

    #[DataProvider('validUrls')]
    public function test_valid_urls_are_accepted(string $url): void
    {
        $this->assertTrue(MenuUrl::isValid($url));
    }

    #[DataProvider('invalidUrls')]
    public function test_invalid_urls_are_rejected(string $url): void
    {
        $this->assertFalse(MenuUrl::isValid($url));
    }

    public function test_the_longest_allowed_url_is_accepted(): void
    {
        $this->assertTrue(MenuUrl::isValid('/'.str_repeat('a', 2047)));
    }

    public function test_is_external(): void
    {
        $this->assertTrue(MenuUrl::isExternal('https://example.com'));
        $this->assertTrue(MenuUrl::isExternal('mailto:a@b.c'));
        $this->assertFalse(MenuUrl::isExternal('/about'));
        $this->assertFalse(MenuUrl::isExternal('#top'));
        $this->assertNull(MenuUrl::isExternal(null));
        $this->assertNull(MenuUrl::isExternal(''));
    }
}
