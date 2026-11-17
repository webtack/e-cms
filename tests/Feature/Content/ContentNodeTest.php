<?php

namespace Webtack\Ecms\Tests\Feature\Content;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Content\ContentNodeCreator;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class ContentNodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_uuid_is_generated(): void
    {
        $node = ContentNode::factory()->create();

        $this->assertTrue(Str::isUuid($node->fresh()->uuid));
    }

    public function test_an_explicit_uuid_is_kept(): void
    {
        $uuid = (string) Str::uuid7();

        $node = ContentNode::factory()->create(['uuid' => $uuid]);

        $this->assertSame($uuid, $node->fresh()->uuid);
    }

    public function test_a_duplicate_or_invalid_uuid_is_rejected(): void
    {
        $existing = ContentNode::factory()->create();

        foreach ([$existing->uuid, 'not-a-uuid'] as $uuid) {
            try {
                ContentNode::factory()->create(['uuid' => $uuid]);
                $this->fail("Uuid [{$uuid}] must be rejected.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('uuid', $e->errors());
            }
        }
    }

    public function test_the_uuid_cannot_change_and_survives_a_slug_change(): void
    {
        $node = ContentNode::factory()->create();
        $uuid = $node->uuid;

        try {
            $node->fresh()->update(['uuid' => (string) Str::uuid7()]);
            $this->fail('Uuid change must be rejected.');
        } catch (ValidationException) {
        }

        $node->fresh()->update(['slug' => 'renamed']);
        $this->assertSame($uuid, $node->fresh()->uuid);
    }

    public function test_find_by_uuid(): void
    {
        $node = ContentNode::factory()->create();

        $this->assertSame($node->id, ContentNode::findByUuid($node->uuid)->id);
        $this->assertNull(ContentNode::findByUuid((string) Str::uuid7()));
    }

    public function test_the_node_stores_the_type_key(): void
    {
        $type = ContentType::create(['key' => 'article', 'name' => 'Article']);

        $node = ContentNode::factory()->create(['content_type' => $type->key]);

        $this->assertSame('article', $node->fresh()->content_type);
        $this->assertSame('article', $node->contentType->key);
    }

    public function test_the_type_of_a_node_cannot_change(): void
    {
        ContentType::create(['key' => 'other', 'name' => 'Other']);
        $node = ContentNode::factory()->create();

        $this->expectException(ValidationException::class);
        $node->fresh()->update(['content_type' => 'other']);
    }

    public function test_a_duplicate_slug_is_rejected_across_types_and_kinds(): void
    {
        ContentType::create(['key' => 'article', 'name' => 'Article']);
        ContentType::create(['key' => 'static', 'name' => 'Static', 'kind' => 'page']);
        ContentNode::factory()->create(['content_type' => 'article', 'slug' => 'about']);

        foreach (['about', 'ABOUT'] as $slug) {
            try {
                ContentNode::factory()->create(['content_type' => 'static', 'slug' => $slug]);
                $this->fail("Slug [{$slug}] must be rejected.");
            } catch (QueryException|ValidationException) {
            }
        }
        $this->assertSame(1, ContentNode::count());
    }

    public function test_invalid_slug_formats_are_rejected(): void
    {
        foreach (['', 'Has Space', '-lead', 'trail-', 'dou--ble', 'under_score', str_repeat('a', 191)] as $slug) {
            try {
                ContentNode::factory()->create(['slug' => $slug]);
                $this->fail("Slug [{$slug}] must be rejected.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('slug', $e->errors());
            }
        }
    }

    public function test_legacy_slugs_are_saved_only_after_allowing_them(): void
    {
        foreach (['a---b', 'jam-session-', 'etno_wave'] as $slug) {
            try {
                ContentNode::factory()->make(['slug' => $slug])->save();
                $this->fail("Legacy slug [{$slug}] must be rejected without the flag.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('slug', $e->errors());
            }

            $node = ContentNode::factory()->make(['slug' => $slug])->allowLegacySlug();
            $node->save();

            $this->assertSame($slug, $node->fresh()->slug);
        }
    }

    public function test_the_legacy_flag_is_not_stored_and_a_saved_legacy_node_can_be_saved_again(): void
    {
        $node = ContentNode::factory()->make(['slug' => 'a---b'])->allowLegacySlug();
        $node->save();

        $reloaded = ContentNode::findBySlug('a---b');
        $reloaded->is_active = false;
        $reloaded->save();

        $this->assertFalse($reloaded->fresh()->is_active);

        $this->expectException(ValidationException::class);
        $reloaded->slug = 'x---y';
        $reloaded->save();
    }

    public function test_the_legacy_flag_still_rejects_foreign_characters(): void
    {
        foreach (['a/b', 'a b', 'Ab', '', str_repeat('a', 191)] as $slug) {
            try {
                ContentNode::factory()->make(['slug' => $slug])->allowLegacySlug()->save();
                $this->fail("Slug [{$slug}] must be rejected even with the flag.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('slug', $e->errors());
            }
        }
    }

    public function test_the_legacy_flag_keeps_uniqueness_and_the_block_prefix(): void
    {
        ContentType::create(['key' => 'blk', 'name' => 'Blk', 'kind' => 'block']);
        ContentNode::factory()->make(['slug' => 'a---b'])->allowLegacySlug()->save();

        try {
            ContentNode::factory()->make(['slug' => 'a---b'])->allowLegacySlug()->save();
            $this->fail('A duplicate legacy slug must be rejected.');
        } catch (QueryException|ValidationException) {
            $this->assertSame(1, ContentNode::count());
        }

        $this->expectException(ValidationException::class);
        ContentNode::factory()->make(['content_type' => 'blk', 'slug' => 'jam_session'])->allowLegacySlug()->save();
    }

    public function test_the_block_prefix_is_required_for_blocks_and_not_restricted_for_other_kinds(): void
    {
        ContentType::create(['key' => 'blk', 'name' => 'Block', 'kind' => 'block']);
        ContentType::create(['key' => 'article', 'name' => 'Article']);

        ContentNode::factory()->create(['content_type' => 'blk', 'slug' => 'block-footer-menu']);
        ContentNode::factory()->create(['content_type' => 'article', 'slug' => 'footer-menu']);
        ContentNode::factory()->create(['content_type' => 'article', 'slug' => 'block-footer']);

        try {
            ContentNode::factory()->create(['content_type' => 'blk', 'slug' => 'footer-menu-2']);
            $this->fail('A block slug without the prefix must be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('slug', $e->errors());
        }
    }

    public function test_find_by_slug_returns_the_node_with_its_type_and_kind(): void
    {
        ContentType::create(['key' => 'static', 'name' => 'Static', 'kind' => 'page']);
        ContentNode::factory()->create(['content_type' => 'static', 'slug' => 'pro-nas']);

        $this->assertSame('page', ContentNode::findBySlug('pro-nas')->contentType->kind);
        $this->assertNull(ContentNode::findBySlug('missing'));
    }

    public function test_service_attribute_defaults(): void
    {
        $node = ContentNode::factory()->create()->fresh();

        $this->assertTrue($node->is_active);
        $this->assertNotNull($node->published_at);
        $this->assertNotNull($node->author);
    }

    public function test_creator_takes_the_author_from_the_current_user(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $type = ContentType::factory()->create();

        $node = app(ContentNodeCreator::class)->create($type, ['title' => 'Hello']);

        $this->assertSame($user->id, $node->user_id);
        $this->assertTrue($node->is_active);
        $this->assertNotNull($node->published_at);
    }

    public function test_creator_fails_without_an_author(): void
    {
        $type = ContentType::factory()->create();

        $this->expectException(\LogicException::class);

        app(ContentNodeCreator::class)->create($type, ['title' => 'Hello']);
    }

    public function test_an_author_with_nodes_cannot_be_deleted(): void
    {
        $node = ContentNode::factory()->create();

        $this->expectException(QueryException::class);

        $node->author->delete();
    }

    public function test_disabling_a_node_keeps_the_publication_date(): void
    {
        $node = ContentNode::factory()->create(['published_at' => '2026-01-02 10:00:00']);

        $node->update(['is_active' => false]);

        $this->assertSame('2026-01-02 10:00', $node->fresh()->published_at->format('Y-m-d H:i'));
    }

    public function test_unpublished_at_is_nullable_and_defaults_to_null(): void
    {
        $this->assertTrue(Schema::hasColumn('content_nodes', 'unpublished_at'));
        $this->assertNull(ContentNode::factory()->create()->fresh()->unpublished_at);
    }

    public function test_status_follows_the_order_disabled_scheduled_expired_published(): void
    {
        $now = now()->setDate(2026, 6, 1)->setTime(12, 0);
        $make = fn (array $a) => ContentNode::factory()->make($a);

        $this->assertSame('published', $make(['published_at' => $now->copy()->subDay()])->status($now));
        $this->assertSame('published', $make(['published_at' => $now->copy()->subDay(), 'unpublished_at' => $now->copy()->addDay()])->status($now));
        $this->assertSame('scheduled', $make(['published_at' => $now->copy()->addDay(), 'unpublished_at' => $now->copy()->addDays(2)])->status($now));
        $this->assertSame('expired', $make(['published_at' => $now->copy()->subDays(2), 'unpublished_at' => $now->copy()->subDay()])->status($now));
        $this->assertSame('disabled', $make(['is_active' => false, 'published_at' => $now->copy()->subDays(2), 'unpublished_at' => $now->copy()->subDay()])->status($now));
    }

    public function test_the_visibility_interval_is_half_open(): void
    {
        $start = now()->setDate(2026, 6, 1)->setTime(12, 0);
        $end = $start->copy()->addDay();
        $node = ContentNode::factory()->make(['published_at' => $start, 'unpublished_at' => $end]);

        $this->assertSame('scheduled', $node->status($start->copy()->subSecond()));
        $this->assertSame('published', $node->status($start));
        $this->assertSame('published', $node->status($end->copy()->subSecond()));
        $this->assertSame('expired', $node->status($end));
    }

    public function test_an_equal_or_earlier_unpublish_date_is_rejected(): void
    {
        $at = now()->addDay();

        foreach ([$at, $at->copy()->subHour()] as $end) {
            try {
                ContentNode::factory()->create(['published_at' => $at, 'unpublished_at' => $end]);
                $this->fail('Expected a ValidationException.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('unpublished_at', $e->errors());
            }
        }
    }

    public function test_disabling_a_node_keeps_both_dates(): void
    {
        $node = ContentNode::factory()->create(['published_at' => '2026-01-02 10:00:00', 'unpublished_at' => '2026-01-03 10:00:00']);

        $node->update(['is_active' => false]);

        $this->assertSame('2026-01-03 10:00', $node->fresh()->unpublished_at->format('Y-m-d H:i'));
    }

    public function test_find_by_slug_only_visible_skips_an_expired_node(): void
    {
        ContentNode::factory()->create(['slug' => 'old', 'published_at' => now()->subDays(2), 'unpublished_at' => now()->subDay()]);
        ContentNode::factory()->create(['slug' => 'live', 'published_at' => now()->subDays(2), 'unpublished_at' => now()->addDay()]);

        $this->assertNull(ContentNode::findBySlug('old', onlyVisible: true));
        $this->assertNotNull(ContentNode::findBySlug('old'));
        $this->assertNotNull(ContentNode::findBySlug('live', onlyVisible: true));
    }

    public function test_creator_stores_the_unpublish_date_and_rejects_one_in_the_past(): void
    {
        $type = ContentType::factory()->create();
        $creator = app(ContentNodeCreator::class);

        $node = $creator->create($type, ['title' => 'Promo'], authorId: User::factory()->create()->id, unpublishedAt: now()->addDay());
        $this->assertNotNull($node->fresh()->unpublished_at);

        $this->expectException(ValidationException::class);
        $creator->create($type, ['title' => 'Late'], authorId: User::factory()->create()->id, unpublishedAt: now()->subDay());
    }

    public function test_the_column_default_of_views_is_zero_for_every_kind(): void
    {
        foreach (['content', 'page', 'block'] as $kind) {
            ContentType::create(['key' => "t_{$kind}", 'name' => $kind, 'kind' => $kind]);
            $slug = $kind === 'block' ? 'block-one' : "{$kind}-one";

            $node = ContentNode::factory()->create(['content_type' => "t_{$kind}", 'slug' => $slug]);

            $this->assertSame(0, $node->fresh()->views);
        }
    }

    public function test_a_node_created_by_the_creator_starts_with_one_view_for_every_kind(): void
    {
        foreach (['content', 'page', 'block'] as $kind) {
            $type = ContentType::create(['key' => "c_{$kind}", 'name' => $kind, 'kind' => $kind]);

            $node = app(ContentNodeCreator::class)->create($type, ['title' => 'New'], authorId: User::factory()->create()->id);

            $this->assertSame(1, $node->fresh()->views);
        }
    }

    public function test_creator_seeds_the_view_counter(): void
    {
        $type = ContentType::factory()->create();

        $node = app(ContentNodeCreator::class)->create($type, ['title' => 'Old'], authorId: User::factory()->create()->id, views: 1250);

        $this->assertSame(1250, $node->fresh()->views);
    }

    public function test_a_negative_view_counter_is_rejected(): void
    {
        $type = ContentType::factory()->create();

        try {
            app(ContentNodeCreator::class)->create($type, ['title' => 'Bad'], authorId: User::factory()->create()->id, views: -1);
            $this->fail('A negative counter must be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('views', $e->errors());
        }

        $this->assertSame(0, ContentNode::query()->count());
    }

    public function test_views_is_not_mass_assignable(): void
    {
        $node = new ContentNode(['views' => 99]);

        $this->assertNull($node->views);
    }

    public function test_increment_views_adds_atomically_and_keeps_the_model_clean(): void
    {
        $node = ContentNode::factory()->create();
        $node->forceFill(['views' => 5])->saveQuietly();

        $node->incrementViews();
        $node->incrementViews(10);

        $this->assertSame(16, $node->views);
        $this->assertFalse($node->isDirty());
        $this->assertSame(16, $node->fresh()->views);
    }

    public function test_increment_views_does_not_touch_other_attributes(): void
    {
        $node = ContentNode::factory()->create(['published_at' => '2026-01-02 10:00:00']);
        $before = $node->fresh();

        $this->travel(1)->hours();
        $node->incrementViews();

        $after = $node->fresh();
        $this->assertEquals($before->updated_at, $after->updated_at);
        $this->assertEquals($before->published_at, $after->published_at);
        $this->assertSame($before->is_active, $after->is_active);
        $this->assertSame(1, $after->views);
    }

    public function test_increment_views_rejects_a_negative_amount(): void
    {
        $node = ContentNode::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        $node->incrementViews(-1);
    }

    public function test_the_views_migration_gives_existing_rows_zero_and_rolls_back(): void
    {
        $path = $this->packagePath('database/migrations/2026_11_06_100000_add_views_to_content_nodes_table.php');
        // The index migration depends on the column, so it goes first on the way down and last on the way up.
        $index = $this->packagePath('database/migrations/2026_11_06_200000_add_views_index_to_content_nodes_table.php');
        $node = ContentNode::factory()->create();

        Artisan::call('migrate:rollback', ['--path' => $index, '--realpath' => true]);
        Artisan::call('migrate:rollback', ['--path' => $path, '--realpath' => true]);
        $this->assertFalse(Schema::hasColumn('content_nodes', 'views'));

        Artisan::call('migrate', ['--path' => $path, '--realpath' => true]);
        Artisan::call('migrate', ['--path' => $index, '--realpath' => true]);
        $this->assertTrue(Schema::hasColumn('content_nodes', 'views'));
        $this->assertSame(0, ContentNode::query()->findOrFail($node->id)->views);
    }

    public function test_user_id_is_not_mass_assignable(): void
    {
        $other = User::factory()->create();
        $type = ContentType::factory()->create();

        $node = new ContentNode(['content_type' => $type->key, 'slug' => 'x', 'user_id' => $other->id]);

        $this->assertNull($node->user_id);
    }

    public function test_a_page_slug_cannot_equal_a_content_type_slug_or_a_reserved_path(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News', 'slug' => 'news']);
        ContentType::create(['key' => 'static', 'name' => 'Static', 'kind' => 'page']);

        foreach (['news', 'admin', 'login', 'storage'] as $slug) {
            try {
                ContentNode::factory()->create(['content_type' => 'static', 'slug' => $slug]);
                $this->fail("Page slug [{$slug}] must be rejected.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('slug', $e->errors());
            }
        }

        $this->assertSame(0, ContentNode::query()->count());
    }

    public function test_content_and_block_nodes_may_use_a_type_slug_or_a_reserved_path(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News', 'slug' => 'news']);
        ContentType::create(['key' => 'hero', 'name' => 'Hero', 'kind' => 'block']);

        ContentNode::factory()->create(['content_type' => 'news', 'slug' => 'admin']);
        ContentNode::factory()->create(['content_type' => 'hero', 'slug' => 'block-admin']);

        $this->assertSame(2, ContentNode::query()->count());
    }

    public function test_an_existing_page_collision_does_not_block_other_edits(): void
    {
        ContentType::create(['key' => 'static', 'name' => 'Static', 'kind' => 'page']);
        $page = ContentNode::factory()->create(['content_type' => 'static', 'slug' => 'about']);
        // Inserted around the model, like data that predates the rule.
        DB::table('content_types')->insert(['key' => 'news', 'name' => 'News', 'slug' => 'about', 'kind' => 'content', 'created_at' => now(), 'updated_at' => now()]);

        $page->update(['is_active' => false]);

        $this->assertFalse($page->fresh()->is_active);

        // Changing the slug to a colliding value is still rejected.
        ContentType::create(['key' => 'blog', 'name' => 'Blog', 'slug' => 'blog']);
        $this->expectException(ValidationException::class);
        $page->update(['slug' => 'blog']);
    }

    public function test_a_node_address_equal_to_a_type_slug_is_rejected(): void
    {
        ContentType::create(['key' => 'common', 'name' => 'Common', 'slug' => 'common']);
        ContentType::create(['key' => 'inst', 'name' => 'Inst', 'slug' => 'common/institutions']);

        try {
            ContentNode::factory()->create(['content_type' => 'common', 'slug' => 'institutions']);
            $this->fail('The node would hide the list of a type.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('slug', $e->errors());
        }

        $free = ContentNode::factory()->create(['content_type' => 'common', 'slug' => 'free']);
        $this->assertSame('free', $free->fresh()->slug);

        try {
            $free->update(['slug' => 'institutions']);
            $this->fail('A slug change must be checked too.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('slug', $e->errors());
        }
    }

    public function test_the_address_check_skips_types_without_a_slug_and_unchanged_slugs(): void
    {
        ContentType::create(['key' => 'bare', 'name' => 'Bare']);
        ContentType::create(['key' => 'common', 'name' => 'Common', 'slug' => 'common']);
        $node = ContentNode::factory()->create(['content_type' => 'common', 'slug' => 'late']);

        $this->assertNotNull(ContentNode::factory()->create(['content_type' => 'bare', 'slug' => 'institutions'])->id);

        // A collision that predates the rule: the type took the slug after the node existed.
        $late = ContentType::create(['key' => 'late_type', 'name' => 'L', 'slug' => 'common/other']);
        DB::table('content_types')->where('id', $late->id)->update(['slug' => 'common/late']);
        $legacy = $node->fresh();
        $legacy->is_active = false;
        $legacy->save();

        $this->assertFalse($legacy->fresh()->is_active);
        $this->assertSame('late', $legacy->fresh()->slug);
    }
}
