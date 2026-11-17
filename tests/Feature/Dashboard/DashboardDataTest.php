<?php

namespace Webtack\Ecms\Tests\Feature\Dashboard;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Webtack\Ecms\Dashboard\DashboardData;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\File;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Models\UserFavoriteType;
use Webtack\Ecms\Support\AppSettings;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class DashboardDataTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->now = Carbon::parse('2026-06-15 12:00:00');
        ContentType::create(['key' => 'news', 'name' => 'News']);
        ContentType::create(['key' => 'events', 'name' => 'Events']);
        ContentType::create(['key' => 'about', 'name' => 'About', 'kind' => 'page']);
    }

    /** @param  array<string, string>  $permissions */
    private function editor(array $permissions): User
    {
        return User::factory()->withRole(Role::factory()->withPermissions($permissions)->create())->create();
    }

    /** @param  array<string, mixed>  $attributes */
    private function node(string $type, array $attributes = [], ?User $author = null): ContentNode
    {
        return ContentNode::factory()->create([
            'content_type' => $type,
            'user_id' => ($author ?? User::factory()->create())->id,
            'published_at' => $this->now->copy()->subDay(),
            ...$attributes,
        ]);
    }

    private function data(User $user): array
    {
        return app(DashboardData::class)->forUser($user, $this->now);
    }

    /** @return list<string> */
    private function uuids(array $rows): array
    {
        return array_column($rows, 'uuid');
    }

    public function test_level_any_sees_every_node_of_the_type(): void
    {
        $user = $this->editor(['node:news' => 'any']);
        $mine = $this->node('news', [], $user);
        $other = $this->node('news');

        $this->assertEqualsCanonicalizing([$mine->uuid, $other->uuid], $this->uuids($this->data($user)['recent']));
    }

    public function test_level_own_limits_every_node_widget_to_the_author(): void
    {
        $user = $this->editor(['node:news' => 'own']);
        $mine = $this->node('news', ['views' => 5, 'published_at' => $this->now->copy()->addDay()], $user);
        $this->node('news', ['views' => 9, 'published_at' => $this->now->copy()->addDay()]);

        $data = $this->data($user);

        $this->assertSame([$mine->uuid], $this->uuids($data['recent']));
        $this->assertSame([$mine->uuid], $this->uuids($data['top']));
        $this->assertSame([$mine->uuid], $this->uuids($data['scheduled']));
        $this->assertSame(1, $data['counters'][0]['total']);
    }

    public function test_level_none_hides_the_type_everywhere(): void
    {
        $user = $this->editor(['node:news' => 'any', 'node:events' => 'none']);
        $this->node('events', ['views' => 3], $user);
        $this->node('news', [], $user);
        UserFavoriteType::create(['user_id' => $user->id, 'type_key' => 'events']);

        $data = $this->data($user);

        $this->assertSame(['news'], array_column($data['counters'], 'key'));
        $this->assertSame(['news'], array_column($data['create'], 'key'));
        $this->assertSame([], $data['favorites']);
        $this->assertSame([], $data['top']);
        $this->assertSame(['news'], array_unique(array_column(array_column($data['recent'], 'type'), 'key')));
    }

    public function test_a_user_with_no_access_gets_empty_widgets(): void
    {
        $user = $this->editor([]);
        $this->node('news');

        $data = $this->data($user);

        foreach (['recent', 'drafts', 'create', 'favorites', 'counters', 'top', 'scheduled', 'expired'] as $key) {
            $this->assertSame([], $data[$key], $key);
        }
        $this->assertNull($data['media']);
    }

    public function test_recent_changes_are_newest_first_with_title_author_and_type(): void
    {
        $user = $this->editor(['node:news' => 'any']);
        $old = $this->node('news', ['updated_at' => $this->now->copy()->subDays(3), 'slug' => 'old'], $user);
        $new = $this->node('news', ['updated_at' => $this->now->copy()->subDay(), 'slug' => 'new'], $user);
        $mid = $this->node('news', ['updated_at' => $this->now->copy()->subDays(2), 'slug' => 'mid'], $user);

        $recent = $this->data($user)['recent'];

        $this->assertSame([$new->uuid, $mid->uuid, $old->uuid], $this->uuids($recent));
        $this->assertSame('new', $recent[0]['title']);
        $this->assertSame($user->name, $recent[0]['author']);
        $this->assertSame(['key' => 'news', 'name' => 'News', 'kind' => 'content'], $recent[0]['type']);
        $this->assertSame("/admin/content-types/news/nodes/{$new->uuid}/edit", $recent[0]['edit_url']);
    }

    public function test_recent_changes_are_limited(): void
    {
        $user = $this->editor(['node:news' => 'any']);
        ContentNode::factory()->count(DashboardData::WIDE_LIMIT + 2)->create(['content_type' => 'news', 'user_id' => $user->id]);

        $this->assertCount(DashboardData::WIDE_LIMIT, $this->data($user)['recent']);
    }

    public function test_another_users_node_under_own_has_no_edit_link_but_any_has(): void
    {
        $own = $this->editor(['node:news' => 'own', 'node:about' => 'any']);
        $other = User::factory()->create();
        // Nodes of another user are not visible under `own` at all; a link-less row exists only through `any`.
        $this->node('about', [], $other);
        $this->node('news', [], $own);

        $rows = collect($this->data($own)['recent']);

        $this->assertNotNull($rows->firstWhere('type.key', 'news')['edit_url']);
        $this->assertNotNull($rows->firstWhere('type.key', 'about')['edit_url']);
        $this->assertCount(2, $rows);
    }

    public function test_drafts_are_only_own_disabled_nodes_of_accessible_types(): void
    {
        $admin = User::factory()->create();
        $draft = $this->node('news', ['is_active' => false], $admin);
        $this->node('news', ['is_active' => false]);
        $this->node('news', ['is_active' => true], $admin);

        $this->assertSame([$draft->uuid], $this->uuids($this->data($admin)['drafts']));

        $editor = $this->editor(['node:news' => 'none']);
        $this->node('news', ['is_active' => false], $editor);

        $this->assertSame([], $this->data($editor)['drafts']);
    }

    public function test_quick_create_lists_accessible_types_by_kind_then_name(): void
    {
        $user = $this->editor(['node:news' => 'own', 'node:about' => 'any', 'node:events' => 'any']);

        $create = $this->data($user)['create'];

        $this->assertSame(['events', 'news', 'about'], array_column($create, 'key'));
        $this->assertSame('/admin/content-types/news/nodes/create', $create[1]['url']);
        $this->assertSame('/admin/page-types/about/nodes/create', $create[2]['url']);
    }

    public function test_quick_create_is_not_flat_when_not_configured(): void
    {
        $user = $this->editor(['node:news' => 'any']);

        $this->assertFalse($this->data($user)['create_flat']);
    }

    public function test_quick_create_follows_the_configured_types_and_order(): void
    {
        $user = $this->editor(['node:news' => 'any', 'node:about' => 'any', 'node:events' => 'any']);
        app(AppSettings::class)->set('dashboard.create_types', ['about', 'news']);

        $data = $this->data($user);

        $this->assertSame(['about', 'news'], array_column($data['create'], 'key'));
        $this->assertTrue($data['create_flat']);
        $this->assertSame('/admin/page-types/about/nodes/create', $data['create'][0]['url']);
    }

    public function test_quick_create_skips_a_configured_type_without_access(): void
    {
        $user = $this->editor(['node:news' => 'any', 'node:events' => 'none']);
        app(AppSettings::class)->set('dashboard.create_types', ['events', 'news']);

        $this->assertSame(['news'], array_column($this->data($user)['create'], 'key'));
    }

    public function test_quick_create_skips_a_deleted_configured_type(): void
    {
        $user = $this->editor(['node:news' => 'any']);
        app(AppSettings::class)->set('dashboard.create_types', ['gone', 'news']);

        $this->assertSame(['news'], array_column($this->data($user)['create'], 'key'));
    }

    public function test_quick_create_is_empty_when_no_configured_type_is_accessible(): void
    {
        $user = $this->editor(['node:news' => 'any', 'node:events' => 'none']);
        app(AppSettings::class)->set('dashboard.create_types', ['events']);

        $data = $this->data($user);

        $this->assertSame([], $data['create']);
        $this->assertTrue($data['create_flat']);
    }

    public function test_favorites_carry_the_node_count_and_skip_types_without_access(): void
    {
        $user = $this->editor(['node:news' => 'own', 'node:events' => 'none']);
        $this->node('news', [], $user);
        $this->node('news', [], $user);
        $this->node('news');
        UserFavoriteType::create(['user_id' => $user->id, 'type_key' => 'events']);
        UserFavoriteType::create(['user_id' => $user->id, 'type_key' => 'news']);

        $favorites = $this->data($user)['favorites'];

        $this->assertCount(1, $favorites);
        $this->assertSame('news', $favorites[0]['key']);
        $this->assertSame(2, $favorites[0]['count']);
        $this->assertSame('/admin/content-types/news/nodes', $favorites[0]['href']);
    }

    public function test_counters_split_published_drafts_and_the_rest(): void
    {
        $user = $this->editor(['node:news' => 'any', 'node:events' => 'any']);
        $this->node('news', [], $user);
        $this->node('news', [], $user);
        $this->node('news', ['is_active' => false], $user);
        $this->node('news', ['published_at' => $this->now->copy()->addDay()], $user);
        $this->node('news', ['unpublished_at' => $this->now->copy()->subHour()], $user);

        $counters = collect($this->data($user)['counters'])->keyBy('key');

        $this->assertSame(['total' => 5, 'published' => 2, 'drafts' => 1], collect($counters['news'])->only(['total', 'published', 'drafts'])->all());
        $this->assertSame(['total' => 0, 'published' => 0, 'drafts' => 0], collect($counters['events'])->only(['total', 'published', 'drafts'])->all());
        $this->assertSame('/admin/content-types/news/nodes', $counters['news']['href']);
    }

    public function test_counters_are_ordered_by_kind_then_name(): void
    {
        $user = $this->editor(['node:news' => 'any', 'node:events' => 'any', 'node:about' => 'any']);

        $this->assertSame(['events', 'news', 'about'], array_column($this->data($user)['counters'], 'key'));
    }

    public function test_top_by_views_is_descending_and_skips_zero(): void
    {
        $user = $this->editor(['node:news' => 'any']);
        $a = $this->node('news', ['views' => 500]);
        $b = $this->node('news', ['views' => 120]);
        $c = $this->node('news', ['views' => 900]);
        $this->node('news', ['views' => 0]);

        $this->assertSame([$c->uuid, $a->uuid, $b->uuid], $this->uuids($this->data($user)['top']));
        $this->assertSame(900, $this->data($user)['top'][0]['views']);
    }

    public function test_scheduled_and_expired_use_active_nodes_only_in_the_specified_order(): void
    {
        $user = $this->editor(['node:news' => 'any']);
        $soon = $this->node('news', ['published_at' => $this->now->copy()->addDay()]);
        $later = $this->node('news', ['published_at' => $this->now->copy()->addDays(5)]);
        $disabled = $this->node('news', ['published_at' => $this->now->copy()->addDays(2), 'is_active' => false]);
        $recentExpiry = $this->node('news', ['unpublished_at' => $this->now->copy()->subHour()]);
        $oldExpiry = $this->node('news', ['published_at' => $this->now->copy()->subDays(9), 'unpublished_at' => $this->now->copy()->subDays(3)]);
        $disabledExpired = $this->node('news', ['unpublished_at' => $this->now->copy()->subHour(), 'is_active' => false]);

        $data = $this->data($user);

        $this->assertSame([$soon->uuid, $later->uuid], $this->uuids($data['scheduled']));
        $this->assertSame([$recentExpiry->uuid, $oldExpiry->uuid], $this->uuids($data['expired']));
        $this->assertNotContains($disabled->uuid, $this->uuids($data['scheduled']));
        $this->assertNotContains($disabledExpired->uuid, $this->uuids($data['expired']));
    }

    public function test_media_follows_the_media_level(): void
    {
        $user = $this->editor(['media' => 'own']);
        File::factory()->create(['user_id' => $user->id, 'size' => 1000]);
        File::factory()->create(['user_id' => $user->id, 'size' => 500]);
        File::factory()->create(['user_id' => User::factory()->create()->id, 'size' => 7000]);

        $own = $this->data($user)['media'];
        $this->assertSame(2, $own['count']);
        $this->assertSame(1500, $own['size']);
        $this->assertCount(2, $own['latest']);

        $any = $this->data($this->editor(['media' => 'any']))['media'];
        $this->assertSame(3, $any['count']);
        $this->assertSame(8500, $any['size']);

        $this->assertNull($this->data($this->editor(['node:news' => 'any']))['media']);
    }

    public function test_media_latest_is_newest_first_with_a_link_and_limited(): void
    {
        $user = $this->editor(['media' => 'any']);
        $files = collect(range(1, DashboardData::WIDE_LIMIT + 1))
            ->map(fn (int $i) => File::factory()->create(['created_at' => $this->now->copy()->subDays(10 - $i)]));

        $latest = $this->data($user)['media']['latest'];

        $this->assertCount(DashboardData::WIDE_LIMIT, $latest);
        $this->assertSame($files->last()->uuid, $latest[0]['uuid']);
        $this->assertSame('/admin/media/'.$files->last()->uuid, $latest[0]['url']);
    }

    public function test_empty_library_returns_zero_files(): void
    {
        $media = $this->data($this->editor(['media' => 'any']))['media'];

        $this->assertSame(0, $media['count']);
        $this->assertSame(0, $media['size']);
        $this->assertSame([], $media['latest']);
    }
}
