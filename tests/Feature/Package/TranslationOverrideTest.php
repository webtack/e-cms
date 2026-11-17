<?php

namespace Webtack\Ecms\Tests\Feature\Package;

use Webtack\Ecms\Support\InterfaceTranslations;
use Webtack\Ecms\Tests\Concerns\OverridesHostFiles;
use Webtack\Ecms\Tests\TestCase;

/**
 * Interface translations come from the package. The host's `lang/` overrides the JSON keys it defines; the PHP groups
 * `auth` and `validation` of the package win over the same keys of the host (see the provider).
 */
class TranslationOverrideTest extends TestCase
{
    use OverridesHostFiles;

    /** @return array<string, string> */
    private function package(string $file): array
    {
        return json_decode((string) file_get_contents($this->packagePath("lang/{$file}")), true);
    }

    public function test_the_package_json_dictionary_is_used_without_a_host_file(): void
    {
        $this->hideHostFile('lang/uk.json');
        app()->setLocale('uk');

        $this->assertSame($this->package('uk.json')['No role'], __('No role'));
    }

    public function test_a_host_json_key_wins_over_the_package(): void
    {
        $this->hostFile('lang/uk.json', json_encode(['No role' => 'Host says: no role']));
        app()->setLocale('uk');

        $this->assertSame('Host says: no role', __('No role'));
        // A key the host did not override stays the package's.
        $this->assertSame($this->package('uk.json')['Dashboard'], __('Dashboard'));
    }

    public function test_a_php_key_of_the_package_wins_over_the_same_key_of_the_host(): void
    {
        $this->hostFile('lang/en/auth.php', "<?php\n\nreturn ['failed' => 'HOST failed', 'host_only' => 'HOST only'];\n");
        app()->setLocale('en');

        $package = require $this->packagePath('lang/en/auth.php');

        $this->assertSame($package['failed'], __('auth.failed'));
        $this->assertNotSame('HOST failed', __('auth.failed'));
        // Keys only the host defines are kept.
        $this->assertSame('HOST only', __('auth.host_only'));
    }

    public function test_the_interface_messages_shared_with_the_client_carry_the_host_override(): void
    {
        $this->hostFile('lang/uk.json', json_encode(['No role' => 'Host says: no role']));

        $messages = (array) InterfaceTranslations::messages('uk');

        $this->assertSame('Host says: no role', $messages['No role']);
        $this->assertSame($this->package('uk.json')['Dashboard'], $messages['Dashboard']);
    }
}
