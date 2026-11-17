<?php

namespace Webtack\Ecms\Tests\Feature\Menus;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Webtack\Ecms\Models\Menu;
use Webtack\Ecms\Models\MenuItem;
use Webtack\Ecms\Tests\TestCase;

class MenuItemStatusTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->now = Carbon::parse('2026-06-15 12:00:00');
    }

    private function item(array $attributes = []): MenuItem
    {
        return MenuItem::factory()->in(Menu::factory()->create())->create($attributes);
    }

    public function test_an_item_without_dates_is_published(): void
    {
        $this->assertSame('published', $this->item()->status($this->now));
    }

    public function test_a_future_publish_date_is_scheduled(): void
    {
        $this->assertSame('scheduled', $this->item(['published_at' => '2026-06-15 12:01:00'])->status($this->now));
    }

    public function test_the_publish_moment_itself_is_published(): void
    {
        $this->assertSame('published', $this->item(['published_at' => '2026-06-15 12:00:00'])->status($this->now));
    }

    public function test_a_passed_unpublish_date_is_expired_including_the_moment_itself(): void
    {
        $this->assertSame('expired', $this->item(['unpublished_at' => '2026-06-15 11:59:00'])->status($this->now));
        $this->assertSame('expired', $this->item(['unpublished_at' => '2026-06-15 12:00:00'])->status($this->now));
        $this->assertSame('published', $this->item(['unpublished_at' => '2026-06-15 12:01:00'])->status($this->now));
    }

    public function test_disabled_wins_over_the_dates(): void
    {
        $this->assertSame('disabled', $this->item(['is_active' => false])->status($this->now));
        $this->assertSame('disabled', $this->item(['is_active' => false, 'published_at' => '2026-07-01 00:00:00'])->status($this->now));
        $this->assertSame('disabled', $this->item(['is_active' => false, 'unpublished_at' => '2026-01-01 00:00:00'])->status($this->now));
    }

    public function test_the_visible_scope_matches_the_status(): void
    {
        $published = $this->item();
        $this->item(['is_active' => false]);
        $this->item(['published_at' => '2026-07-01 00:00:00']);
        $this->item(['unpublished_at' => '2026-01-01 00:00:00']);
        $window = $this->item(['published_at' => '2026-06-01 00:00:00', 'unpublished_at' => '2026-07-01 00:00:00']);

        $this->assertEqualsCanonicalizing(
            [$published->uuid, $window->uuid],
            MenuItem::query()->visible($this->now)->pluck('uuid')->all(),
        );
    }
}
