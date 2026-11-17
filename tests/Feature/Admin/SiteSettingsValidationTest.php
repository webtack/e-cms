<?php

namespace Webtack\Ecms\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Webtack\Ecms\Models\File;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class SiteSettingsValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    /** @param array<string, mixed> $data */
    private function save(array $data)
    {
        return $this->from('/admin/settings')->put('/admin/settings', $data);
    }

    /** @param list<string> $keys */
    private function assertNoErrorsFor(array $keys): void
    {
        $errors = session('errors')?->getBag('default');

        foreach ($keys as $key) {
            $this->assertFalse($errors?->has($key) ?? false, "Unexpected error for [{$key}].");
        }
    }

    private function file(string $profile = 'image'): File
    {
        $uuid = File::newUuid();

        return File::factory()->create(['uuid' => $uuid, 'profile' => $profile, 'mime' => 'image/png', 'path' => "{$uuid}.png", 'size' => 1000]);
    }

    public function test_a_valid_form_passes_and_everything_is_optional(): void
    {
        $this->save(['indexable' => false])->assertSessionDoesntHaveErrors();
        $this->save([])->assertSessionDoesntHaveErrors();
    }

    public function test_the_texts_have_bounds(): void
    {
        $this->save(['name' => str_repeat('a', 256), 'short_name' => str_repeat('a', 101), 'description' => str_repeat('a', 2001), 'address' => str_repeat('a', 501)])
            ->assertSessionHasErrors(['name', 'short_name', 'description', 'address']);
    }

    public function test_a_logo_must_be_a_stored_image(): void
    {
        $document = $this->file('document');

        $this->save(['logo_large' => $document->path, 'logo_small' => 'not-a-file'])
            ->assertSessionHasErrors(['logo_large', 'logo_small']);
        $this->save(['logo_large' => str_repeat('a', 32).'.png'])->assertSessionHasErrors('logo_large');
        $this->save(['logo_large' => $this->file()->path])->assertSessionDoesntHaveErrors();
    }

    public function test_emails_are_validated_per_row(): void
    {
        $this->save(['emails' => [['value' => 'a@b.co'], ['value' => 'nope'], ['value' => '', 'label' => 'Orphan']]])
            ->assertSessionHasErrors(['emails.1.value', 'emails.2.value'])
            ->tap(fn ($response) => $this->assertNoErrorsFor(['emails.0.value']));
        $this->save(['emails' => [['value' => 'a@b.co', 'label' => str_repeat('a', 256)]]])->assertSessionHasErrors('emails.0.label');
    }

    public function test_phones_accept_digits_and_signs_only(): void
    {
        $this->save(['phones' => [['value' => '+380 (44) 000-00-00'], ['value' => '12abc'], ['value' => '12']]])
            ->assertSessionHasErrors(['phones.1.value', 'phones.2.value'])
            ->tap(fn ($response) => $this->assertNoErrorsFor(['phones.0.value']));
    }

    public function test_socials_need_a_known_network_and_an_absolute_http_url(): void
    {
        $this->save(['socials' => [
            ['network' => 'instagram', 'url' => 'https://i.example/a'],
            ['network' => 'instagram', 'url' => 'https://i.example/b'],
            ['network' => 'nope', 'url' => 'https://x.example'],
            ['network' => '', 'url' => 'https://x.example'],
            ['network' => 'facebook', 'url' => 'facebook.com/x'],
            ['network' => 'facebook', 'url' => 'mailto:a@b.c'],
        ]])
            ->assertSessionHasErrors(['socials.2.network', 'socials.3.network', 'socials.4.url', 'socials.5.url'])
            ->tap(fn ($response) => $this->assertNoErrorsFor(['socials.0.network', 'socials.0.url', 'socials.1.url']));
    }

    public function test_the_lists_have_a_size_limit(): void
    {
        $this->save(['emails' => array_fill(0, 21, ['value' => 'a@b.co'])])->assertSessionHasErrors('emails');
        $this->save(['phones' => array_fill(0, 21, ['value' => '123'])])->assertSessionHasErrors('phones');
        $this->save(['socials' => array_fill(0, 21, ['network' => 'x', 'url' => 'https://x.example'])])->assertSessionHasErrors('socials');
        $this->save(['schedule' => array_fill(0, 21, ['a', '', '', ''])])->assertSessionHasErrors('schedule');
        $this->save(['emails' => array_fill(0, 20, ['value' => 'a@b.co'])])->assertSessionDoesntHaveErrors();
    }

    public function test_a_schedule_row_has_four_bounded_columns(): void
    {
        $this->save(['schedule' => [['a', 'b', 'c'], ['a', 'b', 'c', 'd', 'e'], ['a', str_repeat('x', 256), '', '']]])
            ->assertSessionHasErrors(['schedule.0', 'schedule.1', 'schedule.2.1']);
    }

    public function test_blank_schedule_rows_are_dropped_before_validation(): void
    {
        $this->save(['schedule' => [['', '', '', ''], ['', ' ', '', '']]])->assertSessionDoesntHaveErrors();
        $this->save(['schedule' => [['', '', '', ''], ['Mon', '', '', '']]])->assertSessionDoesntHaveErrors();
    }

    public function test_the_indexable_flag_is_a_boolean(): void
    {
        $this->save(['indexable' => 'maybe'])->assertSessionHasErrors('indexable');
    }
}
