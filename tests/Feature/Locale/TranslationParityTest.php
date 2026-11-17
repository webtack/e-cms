<?php

namespace Webtack\Ecms\Tests\Feature\Locale;

use Webtack\Ecms\Tests\TestCase;

class TranslationParityTest extends TestCase
{
    public function test_every_available_locale_has_the_same_keys_as_english(): void
    {
        $reference = $this->keys('en');

        $this->assertNotEmpty($reference);

        foreach (array_keys(config('ecms.locales')) as $locale) {
            $keys = $this->keys($locale);

            $this->assertSame([], array_values(array_diff($reference, $keys)), "lang/{$locale}.json is missing keys.");
            $this->assertSame([], array_values(array_diff($keys, $reference)), "lang/{$locale}.json has keys absent from en.json.");
        }
    }

    public function test_english_values_equal_their_keys(): void
    {
        foreach (json_decode(file_get_contents($this->packagePath('lang/en.json')), true) as $key => $value) {
            $this->assertSame($key, $value);
        }
    }

    /**
     * @return array<int, string>
     */
    private function keys(string $locale): array
    {
        $keys = array_keys(json_decode(file_get_contents($this->packagePath("lang/{$locale}.json")), true, flags: JSON_THROW_ON_ERROR));
        sort($keys);

        return $keys;
    }
}
