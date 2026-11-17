<?php

namespace Webtack\Ecms\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Webtack\Ecms\Models\File;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class YoutubeLookupTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'test-secret-api-key';

    private const WATCH = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config(['services.youtube.key' => self::KEY]);
    }

    private function jpeg(): string
    {
        $image = imagecreatetruecolor(16, 9);
        ob_start();
        imagejpeg($image);

        return (string) ob_get_clean();
    }

    /** @param array<string, mixed> $over */
    private function fakeYoutube(array $over = []): void
    {
        Http::fake($over + [
            'www.googleapis.com/*' => Http::response(['items' => [['snippet' => [
                'title' => 'Never Gonna Give You Up',
                'description' => 'Official video',
                'thumbnails' => [
                    'default' => ['url' => 'https://i.ytimg.com/vi/dQw4w9WgXcQ/default.jpg'],
                    'maxres' => ['url' => 'https://i.ytimg.com/vi/dQw4w9WgXcQ/maxresdefault.jpg'],
                ],
            ]]]]),
            'i.ytimg.com/*/maxresdefault.jpg' => Http::response($this->jpeg(), 200, ['Content-Type' => 'image/jpeg']),
            'i.ytimg.com/*' => Http::response($this->jpeg(), 200, ['Content-Type' => 'image/jpeg']),
        ]);
    }

    private function lookup(string $url = self::WATCH): TestResponse
    {
        return $this->actingAs(User::factory()->create())->postJson(route('admin.youtube.lookup'), ['url' => $url]);
    }

    public function test_a_video_is_read_and_its_screen_is_stored(): void
    {
        $this->fakeYoutube();

        $response = $this->lookup()->assertOk()->assertJson([
            'url' => self::WATCH,
            'title' => 'Never Gonna Give You Up',
            'description' => 'Official video',
            'screen' => ['mime' => 'image/jpeg'],
        ]);

        $uuid = $response->json('screen.uuid');
        $this->assertSame("{$uuid}.jpg", $response->json('screen.value'));
        $this->assertSame("/storage/styles/thumb/{$uuid}.webp", $response->json('screen.preview'));
        $this->assertSame('image', File::findByUuid($uuid)->profile);
        $this->assertSame(1, File::query()->count());
        // The largest screenshot was taken.
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'maxresdefault.jpg'));
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'id=dQw4w9WgXcQ') && str_contains($r->url(), 'part=snippet'));
    }

    public function test_a_smaller_screen_is_used_when_the_largest_is_missing(): void
    {
        $this->fakeYoutube(['i.ytimg.com/*/maxresdefault.jpg' => Http::response('', 404)]);

        $this->lookup()->assertOk();

        $this->assertSame(1, File::query()->count());
    }

    public function test_a_shorts_link_is_turned_into_a_watch_link(): void
    {
        $this->fakeYoutube();

        $this->lookup('https://www.youtube.com/shorts/dQw4w9WgXcQ')->assertOk()->assertJson(['url' => self::WATCH]);
    }

    public function test_a_text_that_is_not_a_video_link_is_refused_without_calls(): void
    {
        Http::fake();

        $this->lookup('https://www.youtube.com/@channel')->assertStatus(422)->assertJson(['reason' => 'invalid_url']);

        Http::assertNothingSent();
        $this->assertSame(0, File::query()->count());
    }

    public function test_an_unknown_video_creates_nothing(): void
    {
        Http::fake(['www.googleapis.com/*' => Http::response(['items' => []])]);

        $this->lookup()->assertStatus(422)->assertJson(['reason' => 'not_found']);

        $this->assertSame(0, File::query()->count());
    }

    public function test_a_missing_key_is_reported_without_calls(): void
    {
        config(['services.youtube.key' => null]);
        Http::fake();

        $this->lookup()->assertStatus(503)->assertJson(['reason' => 'not_configured']);

        Http::assertNothingSent();
    }

    public function test_quota_and_network_errors_are_reported_as_unavailable(): void
    {
        Http::fake(['www.googleapis.com/*' => Http::response(['error' => ['code' => 403]], 403)]);
        $this->lookup()->assertStatus(503)->assertJson(['reason' => 'unavailable']);

        Http::fake(['www.googleapis.com/*' => fn () => throw new ConnectionException('down')]);
        $this->lookup()->assertStatus(503)->assertJson(['reason' => 'unavailable']);

        $this->assertSame(0, File::query()->count());
    }

    public function test_a_failed_screen_download_creates_nothing(): void
    {
        $this->fakeYoutube(['i.ytimg.com/*' => Http::response('', 500)]);
        $this->lookup()->assertStatus(503)->assertJson(['reason' => 'unavailable']);

        $this->fakeYoutube(['i.ytimg.com/*' => Http::response('not an image', 200)]);
        $this->lookup()->assertStatus(503)->assertJson(['reason' => 'unavailable']);

        $this->assertSame(0, File::query()->count());
    }

    public function test_a_screen_address_outside_youtube_is_never_fetched(): void
    {
        Http::fake(['www.googleapis.com/*' => Http::response(['items' => [['snippet' => [
            'title' => 'T', 'description' => 'D',
            'thumbnails' => ['maxres' => ['url' => 'https://evil.example/x.jpg'], 'high' => ['url' => 'http://i.ytimg.com/x.jpg']],
        ]]]])]);

        $this->lookup()->assertStatus(503);

        Http::assertSentCount(1);
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        Http::fake();

        $this->post(route('admin.youtube.lookup'), ['url' => self::WATCH])->assertRedirect(route('login'));

        Http::assertNothingSent();
    }

    public function test_the_key_is_not_exposed(): void
    {
        $this->fakeYoutube();

        $this->assertStringNotContainsString(self::KEY, $this->lookup()->getContent());

        $page = $this->actingAs(User::factory()->create())->get(route('admin.dashboard'));
        $this->assertStringNotContainsString(self::KEY, $page->getContent());
    }
}
