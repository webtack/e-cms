<?php

namespace Webtack\Ecms\Tests\Unit\Files;

use InvalidArgumentException;
use Webtack\Ecms\Files\FileProfiles;
use Webtack\Ecms\Tests\TestCase;

class FileProfilesTest extends TestCase
{
    public function test_it_returns_a_configured_profile(): void
    {
        $profile = (new FileProfiles)->get('image');

        $this->assertSame('public', $profile['disk']);
        $this->assertArrayNotHasKey('directory', $profile);
        $this->assertSame(30 * 1024, $profile['max_size']);
        $this->assertArrayHasKey('image/png', $profile['mimes']);
    }

    public function test_the_image_profile_has_pixel_limits_and_the_processing_settings_exist(): void
    {
        $profile = (new FileProfiles)->get('image');

        $this->assertSame(1920, $profile['max_width']);
        $this->assertSame(1920, $profile['max_height']);
        $this->assertArrayNotHasKey('max_width', (new FileProfiles)->get('document'));
        $this->assertSame('imagick', config('files.image.driver'));
        $this->assertSame(85, config('files.image.quality.jpeg'));
        $this->assertSame(85, config('files.image.quality.webp'));
        $this->assertSame(100_000_000, config('files.image.max_pixels'));
    }

    public function test_limits_come_from_the_environment(): void
    {
        $original = [
            'FILES_IMAGE_MAX_WIDTH' => getenv('FILES_IMAGE_MAX_WIDTH'),
            'FILES_IMAGE_MAX_SIZE' => getenv('FILES_IMAGE_MAX_SIZE'),
        ];

        try {
            // env() reads $_SERVER/$_ENV before getenv(), and a value from `.env` lives there, so set all three.
            foreach (['FILES_IMAGE_MAX_WIDTH' => 'null', 'FILES_IMAGE_MAX_SIZE' => '1024'] as $name => $value) {
                putenv("{$name}={$value}");
                $_ENV[$name] = $_SERVER[$name] = $value;
            }
            $file = $this->packagePath('config/files.php');
            $config = (static fn () => require $file)();

            $this->assertNull($config['profiles']['image']['max_width']);
            $this->assertSame(1024, $config['profiles']['image']['max_size']);
        } finally {
            foreach ($original as $name => $value) {
                if ($value === false) {
                    putenv($name);
                    unset($_ENV[$name], $_SERVER[$name]);
                } else {
                    putenv("{$name}={$value}");
                    $_ENV[$name] = $_SERVER[$name] = $value;
                }
            }
        }
    }

    public function test_the_starting_profiles_exist(): void
    {
        $this->assertEqualsCanonicalizing(['image', 'avatar', 'document'], (new FileProfiles)->keys());
    }

    public function test_an_unknown_profile_throws(): void
    {
        $profiles = new FileProfiles;

        $this->assertFalse($profiles->has('nope'));
        $this->expectException(InvalidArgumentException::class);
        $profiles->get('nope');
    }

    public function test_a_profile_added_to_config_is_available_without_other_changes(): void
    {
        config(['files.profiles.logo' => ['disk' => 'public', 'mimes' => ['image/png' => 'png'], 'max_size' => 100]]);

        $this->assertTrue((new FileProfiles)->has('logo'));
    }

    public function test_every_profile_points_at_a_configured_disk_and_is_well_formed(): void
    {
        foreach ((new FileProfiles)->keys() as $key) {
            $profile = (new FileProfiles)->get($key);

            $this->assertArrayHasKey($profile['disk'], config('filesystems.disks'), "Profile [{$key}] uses an unknown disk.");
            $this->assertNotEmpty($profile['mimes'], "Profile [{$key}] allows no types.");
            $this->assertGreaterThan(0, $profile['max_size'], "Profile [{$key}] has no size limit.");
        }
    }
}
