<?php

namespace Webtack\Ecms\Tests\Unit\Files;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Webtack\Ecms\Files\Rules\FileOfProfile;
use Webtack\Ecms\Models\File;
use Webtack\Ecms\Tests\TestCase;

class FileOfProfileTest extends TestCase
{
    use RefreshDatabase;

    private function errors(mixed $value, string $profile = 'image'): array
    {
        return Validator::make(['file_id' => $value], ['file_id' => [new FileOfProfile($profile)]])->errors()->get('file_id');
    }

    public function test_a_file_of_the_required_profile_passes(): void
    {
        $file = File::factory()->create(['profile' => 'image']);

        $this->assertSame([], $this->errors($file->uuid));
    }

    public function test_a_file_of_another_profile_fails(): void
    {
        $file = File::factory()->create(['profile' => 'document']);

        $errors = $this->errors($file->uuid, 'image');

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('image', $errors[0]);
    }

    public function test_a_missing_file_fails(): void
    {
        $this->assertCount(1, $this->errors(str_repeat('0', 32)));
    }

    public function test_values_that_are_not_a_uuid_fail(): void
    {
        $file = File::factory()->create(['profile' => 'image']);
        $dashed = substr($file->uuid, 0, 8).'-'.substr($file->uuid, 8, 4).'-'.substr($file->uuid, 12, 4).'-'.substr($file->uuid, 16, 4).'-'.substr($file->uuid, 20);

        foreach (['abc', '1', 1, 1.5, [], [$file->uuid], true, strtoupper($file->uuid), $dashed, $file->uuid.'.png', substr($file->uuid, 1)] as $value) {
            $this->assertCount(1, $this->errors($value), 'Value '.json_encode($value).' must fail.');
        }
    }

    public function test_empty_values_are_left_to_required_and_nullable(): void
    {
        $rules = ['file_id' => ['nullable', new FileOfProfile('image')]];
        $this->assertTrue(Validator::make(['file_id' => null], $rules)->passes());

        $rules = ['file_id' => ['required', new FileOfProfile('image')]];
        $this->assertTrue(Validator::make(['file_id' => null], $rules)->fails());
    }
}
