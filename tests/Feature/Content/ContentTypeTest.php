<?php

namespace Webtack\Ecms\Tests\Feature\Content;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Tests\TestCase;

class ContentTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_type_is_created_with_an_optional_description(): void
    {
        $type = ContentType::create(['key' => 'article', 'name' => 'Article']);

        $this->assertNull($type->fresh()->description);

        $type->update(['description' => 'Long reads']);
        $this->assertSame('Long reads', $type->fresh()->description);
    }

    public function test_a_duplicate_key_is_rejected(): void
    {
        ContentType::create(['key' => 'article', 'name' => 'Article']);

        $this->expectException(QueryException::class);
        ContentType::create(['key' => 'article', 'name' => 'Other']);
    }

    public function test_invalid_key_formats_are_rejected(): void
    {
        foreach (['My Type', '1news', 'Upper', str_repeat('a', 65)] as $key) {
            try {
                ContentType::create(['key' => $key, 'name' => 'X']);
                $this->fail("Key [{$key}] must be rejected.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('key', $e->errors());
            }
        }
    }

    public function test_the_key_cannot_change_but_the_name_can(): void
    {
        $type = ContentType::create(['key' => 'article', 'name' => 'Article']);

        try {
            $type->update(['key' => 'story']);
            $this->fail('Key change must be rejected.');
        } catch (ValidationException) {
        }
        $this->assertSame('article', $type->fresh()->key);

        $type->fresh()->update(['name' => 'Story']);
        $this->assertSame('Story', $type->fresh()->name);
    }

    public function test_find_by_key(): void
    {
        ContentType::create(['key' => 'article', 'name' => 'Article']);

        $this->assertSame('article', ContentType::findByKey('article')->key);
        $this->assertNull(ContentType::findByKey('missing'));
    }

    public function test_kind_defaults_to_content_and_accepts_page_and_block(): void
    {
        $this->assertSame('content', ContentType::create(['key' => 'a', 'name' => 'A'])->fresh()->kind);

        foreach (['page', 'block'] as $kind) {
            $type = ContentType::create(['key' => "t_{$kind}", 'name' => 'T', 'kind' => $kind]);

            $this->assertSame($kind, $type->fresh()->kind);
            $this->assertTrue($type->fields()->where('key', 'title')->exists());
        }
    }

    public function test_an_invalid_kind_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        ContentType::create(['key' => 'a', 'name' => 'A', 'kind' => 'section']);
    }

    public function test_kind_cannot_change_with_or_without_nodes(): void
    {
        $empty = ContentType::create(['key' => 'empty', 'name' => 'E']);
        $used = ContentType::create(['key' => 'used', 'name' => 'U']);
        ContentNode::factory()->create(['content_type' => 'used']);

        foreach ([$empty, $used] as $type) {
            try {
                $type->update(['kind' => 'page']);
                $this->fail('Kind change must be rejected.');
            } catch (ValidationException) {
            }
            $this->assertSame('content', $type->fresh()->kind);
        }
    }

    public function test_types_can_be_listed_by_kind(): void
    {
        ContentType::create(['key' => 'a', 'name' => 'A']);
        ContentType::create(['key' => 'p1', 'name' => 'P1', 'kind' => 'page']);
        ContentType::create(['key' => 'p2', 'name' => 'P2', 'kind' => 'page']);

        $this->assertSame(['p1', 'p2'], ContentType::ofKind('page')->orderBy('key')->pluck('key')->all());
    }

    public function test_a_child_type_keeps_its_own_fields(): void
    {
        $parent = ContentType::create(['key' => 'article', 'name' => 'Article']);
        ContentField::factory()->create(['content_type' => 'article', 'key' => 'visits', 'type' => 'integer']);
        $child = ContentType::create(['key' => 'news', 'name' => 'News', 'parent_type' => 'article']);

        $this->assertSame('article', $child->fresh()->parent_type);
        $this->assertSame('title', $child->fields()->first()->key);
        $this->assertNull($child->fields()->where('key', 'visits')->first());
        $this->assertSame(['news'], ContentType::childrenOf('article')->pluck('key')->all());
        $this->assertSame(['article'], ContentType::roots()->pluck('key')->all());
        $this->assertNotNull($parent);
    }

    public function test_a_parent_of_another_kind_is_rejected(): void
    {
        ContentType::create(['key' => 'static', 'name' => 'Static', 'kind' => 'page']);

        $this->expectException(ValidationException::class);
        ContentType::create(['key' => 'blk', 'name' => 'Blk', 'kind' => 'block', 'parent_type' => 'static']);
    }

    public function test_cycles_are_rejected(): void
    {
        $a = ContentType::create(['key' => 'a', 'name' => 'A']);
        $b = ContentType::create(['key' => 'b', 'name' => 'B', 'parent_type' => 'a']);

        foreach ([[$a, 'a'], [$a, 'b']] as [$type, $parent]) {
            try {
                $type->fresh()->update(['parent_type' => $parent]);
                $this->fail('Cycle must be rejected.');
            } catch (ValidationException) {
            }
        }
        $this->assertNull($a->fresh()->parent_type);
        $this->assertNotNull($b);
    }

    public function test_a_type_with_children_cannot_be_deleted(): void
    {
        $parent = ContentType::create(['key' => 'a', 'name' => 'A']);
        ContentType::create(['key' => 'b', 'name' => 'B', 'parent_type' => 'a']);

        $this->expectException(QueryException::class);
        $parent->delete();
    }

    public function test_a_type_is_created_with_its_system_title_field(): void
    {
        $type = ContentType::create(['key' => 'article', 'name' => 'Article']);

        $title = $type->fields()->where('key', 'title')->sole();
        $this->assertSame('string', $title->type);
        $this->assertTrue($title->is_required);
        $this->assertFalse($title->is_multiple);
        $this->assertTrue($title->is_translatable);
        $this->assertTrue($title->is_system);
        $this->assertSame(0, $title->weight);

        $this->assertTrue(ContentType::factory()->create()->fields()->where('key', 'title')->exists());
    }

    public function test_the_title_field_cannot_be_deleted_or_retyped(): void
    {
        $title = ContentType::create(['key' => 'article', 'name' => 'Article'])->fields()->where('key', 'title')->sole();

        try {
            $title->delete();
            $this->fail('Title deletion must be rejected.');
        } catch (ValidationException) {
        }
        $this->assertTrue(ContentField::whereKey($title->id)->exists());

        foreach (['type' => 'text', 'is_multiple' => true, 'is_required' => false] as $attribute => $value) {
            try {
                $title->fresh()->update([$attribute => $value]);
                $this->fail("Changing {$attribute} must be rejected.");
            } catch (ValidationException) {
            }
        }

        $title->fresh()->update(['name' => 'Heading', 'weight' => 5]);
        $this->assertSame('Heading', $title->fresh()->name);
    }

    public function test_deleting_a_type_removes_its_title_field(): void
    {
        $type = ContentType::create(['key' => 'article', 'name' => 'Article']);

        $type->delete();

        $this->assertSame(0, ContentField::count());
    }

    public function test_a_type_has_an_optional_slug(): void
    {
        $with = ContentType::create(['key' => 'news', 'name' => 'News', 'slug' => 'news']);
        $without = ContentType::create(['key' => 'article', 'name' => 'Article']);
        $empty = ContentType::create(['key' => 'story', 'name' => 'Story', 'slug' => '']);

        $this->assertSame('news', $with->fresh()->slug);
        $this->assertNull($without->fresh()->slug);
        $this->assertNull($empty->fresh()->slug);
    }

    public function test_a_duplicate_slug_is_rejected_in_any_case(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News', 'slug' => 'news']);

        foreach (['news', 'News'] as $slug) {
            try {
                ContentType::create(['key' => 'other', 'name' => 'Other', 'slug' => $slug]);
                $this->fail("Slug [{$slug}] must be rejected.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('slug', $e->errors());
            }
        }
    }

    public function test_invalid_slug_formats_are_rejected(): void
    {
        foreach (['Про нас', 'news_1', '-news', 'news-', 'a--b', str_repeat('a', 191)] as $slug) {
            try {
                ContentType::create(['key' => 'news', 'name' => 'News', 'slug' => $slug]);
                $this->fail("Slug [{$slug}] must be rejected.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('slug', $e->errors());
            }
        }
    }

    public function test_page_and_block_types_cannot_have_a_slug(): void
    {
        foreach (['page', 'block'] as $kind) {
            try {
                ContentType::create(['key' => "{$kind}_type", 'name' => 'X', 'kind' => $kind, 'slug' => 'x']);
                $this->fail("A {$kind} type must not accept a slug.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('slug', $e->errors());
            }
        }

        $page = ContentType::create(['key' => 'static', 'name' => 'Static', 'kind' => 'page']);

        $this->expectException(ValidationException::class);
        $page->update(['slug' => 'pages']);
    }

    public function test_the_slug_can_change_and_be_cleared_without_touching_key_and_kind(): void
    {
        $type = ContentType::create(['key' => 'news', 'name' => 'News', 'slug' => 'news']);

        $type->fresh()->update(['slug' => 'latest']);
        $this->assertSame('latest', $type->fresh()->slug);

        $type->fresh()->update(['slug' => null]);
        $this->assertNull($type->fresh()->slug);
        $this->assertSame('news', $type->fresh()->key);
        $this->assertSame('content', $type->fresh()->kind);
    }

    public function test_a_type_can_keep_its_own_slug_when_saved_again(): void
    {
        $type = ContentType::create(['key' => 'news', 'name' => 'News', 'slug' => 'news']);

        $type->fresh()->update(['name' => 'Latest news']);

        $this->assertSame('news', $type->fresh()->slug);
    }

    public function test_descendant_keys_cover_every_level(): void
    {
        $root = ContentType::create(['key' => 'a', 'name' => 'A']);
        ContentType::create(['key' => 'b', 'name' => 'B', 'parent_type' => 'a']);
        ContentType::create(['key' => 'c', 'name' => 'C', 'parent_type' => 'a']);
        ContentType::create(['key' => 'd', 'name' => 'D', 'parent_type' => 'b']);
        ContentType::create(['key' => 'e', 'name' => 'E', 'parent_type' => 'd']);
        ContentType::create(['key' => 'other', 'name' => 'Other']);

        $this->assertEqualsCanonicalizing(['b', 'c', 'd', 'e'], $root->descendantKeys());
        $this->assertSame(['e'], ContentType::findByKey('d')->descendantKeys());
        $this->assertSame([], ContentType::findByKey('e')->descendantKeys());
    }

    public function test_system_fields_follow_the_kind_of_the_type(): void
    {
        $expected = [
            'content' => ['title', 'meta_title', 'meta_description', 'meta_image', 'section_title', 'section_description', 'section_cover'],
            'page' => ['title', 'meta_title', 'meta_description', 'meta_image'],
            'block' => ['title'],
        ];

        foreach ($expected as $kind => $keys) {
            $type = ContentType::create(['key' => "t_{$kind}", 'name' => $kind, 'kind' => $kind]);

            $this->assertSame($keys, $type->fields()->pluck('key')->all(), $kind);
        }
    }

    public function test_metadata_fields_are_optional_single_translatable_and_system(): void
    {
        $type = ContentType::create(['key' => 'article', 'name' => 'Article']);
        $types = ['meta_title' => 'string', 'meta_description' => 'text', 'meta_image' => 'image', 'section_title' => 'string', 'section_description' => 'richtext', 'section_cover' => 'image'];

        foreach ($types as $key => $fieldType) {
            $field = $type->fields()->where('key', $key)->sole();

            $this->assertSame($fieldType, $field->type, $key);
            $this->assertFalse($field->is_required, $key);
            $this->assertFalse($field->is_multiple, $key);
            $this->assertTrue($field->is_translatable, $key);
            $this->assertTrue($field->is_system, $key);
        }
    }

    public function test_a_metadata_field_cannot_be_deleted_or_retyped(): void
    {
        $field = ContentType::create(['key' => 'article', 'name' => 'Article'])->fields()->where('key', 'meta_title')->sole();

        try {
            $field->delete();
            $this->fail('Deleting a metadata field must be rejected.');
        } catch (ValidationException) {
        }
        $this->assertTrue(ContentField::whereKey($field->id)->exists());

        foreach (['type' => 'text', 'is_multiple' => true, 'is_required' => true] as $attribute => $value) {
            try {
                $field->fresh()->update([$attribute => $value]);
                $this->fail("Changing {$attribute} must be rejected.");
            } catch (ValidationException) {
            }
        }

        $field->fresh()->update(['name' => 'SEO title', 'weight' => 40]);
        $this->assertSame('SEO title', $field->fresh()->name);
    }

    public function test_list_settings_may_reference_a_metadata_field_of_a_new_type(): void
    {
        $type = ContentType::create([
            'key' => 'article', 'name' => 'Article', 'settings' => ['list' => ['visible' => ['title', 'meta_title']]],
        ]);

        $this->assertSame(['title', 'meta_title'], $type->settings['list']['visible']);
    }

    public function test_list_settings_accept_service_keys_as_columns_and_sort_field(): void
    {
        $type = ContentType::create([
            'key' => 'article', 'name' => 'Article',
            'settings' => ['list' => [
                'visible' => ['title', 'slug', 'status', 'is_active', 'created_at', 'updated_at', 'published_at', 'unpublished_at'],
                'sort' => ['field' => 'unpublished_at', 'direction' => 'desc'],
            ]],
        ]);

        $this->assertCount(8, $type->settings['list']['visible']);

        foreach (ContentType::LIST_SORT_SERVICE_KEYS as $key) {
            $type->update(['settings' => ['list' => ['sort' => ['field' => $key]]]]);
            $this->assertSame($key, $type->fresh()->settings['list']['sort']['field']);
        }
    }

    public function test_list_settings_reject_unknown_columns_and_the_slug_as_sort_field(): void
    {
        $type = ContentType::create(['key' => 'article', 'name' => 'Article']);

        foreach ([
            ['visible' => ['missing_key']],
            ['search' => ['status']],
            ['search' => ['slug']],
            ['sort' => ['field' => 'slug']],
            ['sort' => ['field' => 'missing']],
        ] as $list) {
            try {
                $type->update(['settings' => ['list' => $list]]);
                $this->fail('The list settings '.json_encode($list).' must be rejected.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('settings', $e->errors());
            }
        }
    }

    public function test_public_settings_accept_added_fields_and_keep_other_keys(): void
    {
        $type = ContentType::create(['key' => 'article', 'name' => 'Article', 'settings' => ['list' => ['visible' => ['title', 'slug']]]]);
        ContentField::create(['content_type' => 'article', 'key' => 'price', 'name' => 'Price', 'type' => 'decimal']);

        $public = ['list_fields' => ['price'], 'page_fields' => [], 'sort' => ['field' => 'published_at', 'direction' => 'desc'], 'per_page' => 10, 'pagination' => true];
        $type->update(['settings' => [...$type->settings, 'public' => $public]]);

        $this->assertSame($public, $type->fresh()->settings['public']);
        $this->assertSame(['title', 'slug'], $type->fresh()->settings['list']['visible']);
    }

    public function test_public_settings_reject_system_and_foreign_fields(): void
    {
        $type = ContentType::create(['key' => 'article', 'name' => 'Article']);
        ContentType::create(['key' => 'other', 'name' => 'Other']);
        ContentField::create(['content_type' => 'other', 'key' => 'foreign', 'name' => 'Foreign', 'type' => 'string']);

        foreach ([
            ['list_fields' => ['title']],
            ['page_fields' => ['meta_title']],
            ['page_fields' => ['foreign']],
            ['sort' => ['field' => 'foreign']],
            ['sort' => ['field' => 'title']],
        ] as $public) {
            try {
                $type->update(['settings' => ['public' => $public]]);
                $this->fail('The public settings '.json_encode($public).' must be rejected.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('settings', $e->errors());
            }
        }
    }

    public function test_public_settings_reject_a_bad_direction_and_page_size(): void
    {
        $type = ContentType::create(['key' => 'article', 'name' => 'Article']);

        foreach ([
            ['sort' => ['field' => 'published_at', 'direction' => 'up']],
            ['per_page' => -1],
            ['per_page' => config('content.max_per_page') + 1],
            ['per_page' => '5'],
            ['pagination' => 'yes'],
        ] as $public) {
            try {
                $type->update(['settings' => ['public' => $public]]);
                $this->fail('The public settings '.json_encode($public).' must be rejected.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('settings', $e->errors());
            }
        }
    }

    public function test_public_settings_of_a_page_take_page_fields_only(): void
    {
        $page = ContentType::create(['key' => 'info', 'name' => 'Info', 'kind' => 'page']);
        ContentField::create(['content_type' => 'info', 'key' => 'intro', 'name' => 'Intro', 'type' => 'text']);

        $page->update(['settings' => ['public' => ['page_fields' => ['intro']]]]);
        $this->assertSame(['intro'], $page->fresh()->settings['public']['page_fields']);

        foreach (['list_fields' => [], 'sort' => ['field' => 'published_at'], 'per_page' => 5, 'pagination' => true] as $key => $value) {
            try {
                $page->update(['settings' => ['public' => [$key => $value]]]);
                $this->fail("A page must reject public {$key}.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('settings', $e->errors());
            }
        }
    }

    public function test_a_reserved_key_is_rejected_for_a_new_type_of_any_kind(): void
    {
        foreach (ContentType::RESERVED_KEYS as $key) {
            foreach (['content', 'page', 'block'] as $kind) {
                try {
                    ContentType::create(['key' => $key, 'name' => 'X', 'kind' => $kind]);
                    $this->fail("Key [{$key}] must be rejected for kind [{$kind}].");
                } catch (ValidationException $e) {
                    $this->assertArrayHasKey('key', $e->errors());
                }
            }
        }

        $this->assertSame(0, ContentType::count());
    }

    public function test_a_key_that_only_resembles_a_reserved_one_is_allowed(): void
    {
        ContentType::create(['key' => 'pages_list', 'name' => 'Pages list']);
        ContentType::create(['key' => 'page', 'name' => 'Page']);

        $this->assertSame(2, ContentType::count());
    }

    public function test_an_existing_type_with_a_reserved_key_can_still_be_edited(): void
    {
        DB::table('content_types')->insert(['key' => 'pages', 'name' => 'Pages', 'kind' => 'content', 'created_at' => now(), 'updated_at' => now()]);

        ContentType::findByKey('pages')->update(['name' => 'Renamed']);

        $this->assertSame('Renamed', ContentType::findByKey('pages')->name);
    }

    public function test_a_type_slug_cannot_equal_a_page_slug_or_a_reserved_path(): void
    {
        ContentType::create(['key' => 'static', 'name' => 'Static', 'kind' => 'page']);
        ContentNode::factory()->create(['content_type' => 'static', 'slug' => 'about-us']);

        foreach (['about-us', 'About-Us', 'admin', 'login'] as $slug) {
            try {
                ContentType::create(['key' => 'news', 'name' => 'News', 'slug' => $slug]);
                $this->fail("Type slug [{$slug}] must be rejected.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('slug', $e->errors());
            }
        }

        $this->assertNull(ContentType::findByKey('news'));
    }

    public function test_changing_a_type_slug_to_a_page_slug_is_rejected_but_an_unchanged_collision_is_not(): void
    {
        ContentType::create(['key' => 'static', 'name' => 'Static', 'kind' => 'page']);
        ContentNode::factory()->create(['content_type' => 'static', 'slug' => 'about']);
        $news = ContentType::create(['key' => 'news', 'name' => 'News', 'slug' => 'latest']);

        // A collision that predates the rule: the page took the slug after the type got it.
        $late = ContentNode::factory()->create(['content_type' => 'static', 'slug' => 'late']);
        DB::table('content_nodes')->where('id', $late->id)->update(['slug' => 'latest']);
        $news->update(['name' => 'Latest news']);
        $this->assertSame('Latest news', $news->fresh()->name);

        $this->expectException(ValidationException::class);
        $news->update(['slug' => 'about']);
    }

    /** @param  array<string, mixed>  $attributes */
    private function assertSlugRejected(array $attributes): void
    {
        try {
            ContentType::create($attributes);
            $this->fail('Slug ['.($attributes['slug'] ?? '').'] must be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('slug', $e->errors());
        }
    }

    public function test_a_slug_of_several_segments_is_saved_and_unique_in_any_case(): void
    {
        $type = ContentType::create(['key' => 'inst', 'name' => 'Inst', 'slug' => 'common/institutions']);
        $this->assertSame('common/institutions', $type->fresh()->slug);

        $this->assertSlugRejected(['key' => 'other', 'name' => 'O', 'slug' => 'Common/Institutions']);
    }

    public function test_bad_separators_in_a_slug_are_rejected(): void
    {
        foreach (['/news', 'news/', 'a//b', 'a/-b'] as $slug) {
            $this->assertSlugRejected(['key' => 'bad', 'name' => 'B', 'slug' => $slug]);
        }
    }

    public function test_a_child_slug_must_start_with_the_parent_slug(): void
    {
        ContentType::create(['key' => 'common', 'name' => 'Common', 'slug' => 'common']);

        foreach (['institutions', 'other/institutions', 'common', 'commonplace/x'] as $slug) {
            $this->assertSlugRejected(['key' => 'child', 'name' => 'C', 'parent_type' => 'common', 'slug' => $slug]);
        }

        ContentType::create(['key' => 'child', 'name' => 'C', 'parent_type' => 'common', 'slug' => 'common/institutions']);
        ContentType::create(['key' => 'deep', 'name' => 'D', 'parent_type' => 'child', 'slug' => 'common/institutions/deep']);
        ContentType::create(['key' => 'noslug', 'name' => 'N', 'parent_type' => 'common']);

        $this->assertSame('common/institutions/deep', ContentType::findByKey('deep')->slug);
        $this->assertNull(ContentType::findByKey('noslug')->slug);
    }

    public function test_a_child_slug_needs_a_parent_with_a_slug(): void
    {
        ContentType::create(['key' => 'bare', 'name' => 'Bare']);

        $this->assertSlugRejected(['key' => 'child', 'name' => 'C', 'parent_type' => 'bare', 'slug' => 'common/institutions']);
    }

    public function test_changing_the_parent_rechecks_the_slug_but_removing_it_does_not(): void
    {
        ContentType::create(['key' => 'common', 'name' => 'Common', 'slug' => 'common']);
        ContentType::create(['key' => 'collectives', 'name' => 'Collectives', 'slug' => 'collectives']);
        $child = ContentType::create(['key' => 'inst', 'name' => 'I', 'parent_type' => 'common', 'slug' => 'common/institutions']);

        try {
            $child->fresh()->update(['parent_type' => 'collectives']);
            $this->fail('A parent with another slug must be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('slug', $e->errors());
        }
        $this->assertSame('common', $child->fresh()->parent_type);

        $child->fresh()->update(['parent_type' => null]);
        $this->assertNull($child->fresh()->parent_type);
        $this->assertSame('common/institutions', $child->fresh()->slug);
    }

    public function test_the_slug_of_a_parent_with_slugged_children_cannot_change_or_be_cleared(): void
    {
        $common = ContentType::create(['key' => 'common', 'name' => 'Common', 'slug' => 'common']);
        $child = ContentType::create(['key' => 'inst', 'name' => 'I', 'parent_type' => 'common', 'slug' => 'common/institutions']);

        foreach (['info', null] as $slug) {
            try {
                $common->fresh()->update(['slug' => $slug]);
                $this->fail('The slug of a parent with slugged children must not change.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('slug', $e->errors());
            }
        }
        $this->assertSame('common', $common->fresh()->slug);

        // A child without a slug does not block; once the slugged child is cleared the parent is free.
        $child->fresh()->update(['slug' => null]);
        $common->fresh()->update(['slug' => 'info']);
        $this->assertSame('info', $common->fresh()->slug);
    }

    public function test_a_legacy_child_without_the_prefix_does_not_block_a_name_change(): void
    {
        ContentType::create(['key' => 'common', 'name' => 'Common', 'slug' => 'common']);
        $child = ContentType::create(['key' => 'inst', 'name' => 'I', 'parent_type' => 'common', 'slug' => 'common/institutions']);
        DB::table('content_types')->where('id', $child->id)->update(['slug' => 'legacy']);

        $child->fresh()->update(['name' => 'Renamed']);

        $this->assertSame('Renamed', $child->fresh()->name);
    }

    public function test_only_the_first_segment_is_compared_with_reserved_paths(): void
    {
        $this->assertSlugRejected(['key' => 'reports', 'name' => 'R', 'slug' => 'admin/reports']);

        $type = ContentType::create(['key' => 'admin_news', 'name' => 'N', 'slug' => 'news/admin']);
        $this->assertSame('news/admin', $type->fresh()->slug);
    }

    public function test_a_page_next_to_a_nested_type_is_not_a_conflict(): void
    {
        ContentType::create(['key' => 'static', 'name' => 'Static', 'kind' => 'page']);
        ContentNode::factory()->create(['content_type' => 'static', 'slug' => 'common']);

        $type = ContentType::create(['key' => 'inst', 'name' => 'I', 'slug' => 'common/institutions']);

        $this->assertSame('common/institutions', $type->fresh()->slug);
    }

    public function test_a_type_slug_equal_to_a_node_address_is_rejected(): void
    {
        $common = ContentType::create(['key' => 'common', 'name' => 'Common', 'slug' => 'common']);
        ContentNode::factory()->create(['content_type' => 'common', 'slug' => 'work-plan']);

        $this->assertSlugRejected(['key' => 'plan', 'name' => 'P', 'slug' => 'Common/Work-Plan']);
        $this->assertNotNull(ContentType::create(['key' => 'other', 'name' => 'O', 'slug' => 'common/other'])->id);
        $this->assertNotNull($common->id);
    }

    public function test_changing_a_slug_so_that_own_nodes_hide_a_deeper_type_is_rejected(): void
    {
        $holder = ContentType::create(['key' => 'holder', 'name' => 'H', 'slug' => 'holder']);
        ContentNode::factory()->create(['content_type' => 'holder', 'slug' => 'work-plan']);
        ContentType::create(['key' => 'plan', 'name' => 'P', 'slug' => 'common/work-plan']);

        try {
            $holder->fresh()->update(['slug' => 'common']);
            $this->fail('The type slug must not hide a deeper type.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('slug', $e->errors());
        }

        $holder->fresh()->update(['slug' => 'other']);
        $this->assertSame('other', $holder->fresh()->slug);
    }

    /** @param  array<string, mixed>  $archive */
    private function assertArchiveInvalid(ContentType $type, array $archive): void
    {
        try {
            $type->update(['settings' => ['public' => ['archive' => $archive]]]);
            $this->fail('The archive must be rejected: '.json_encode($archive));
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('settings', $e->errors());
        }
    }

    public function test_the_public_archive_accepts_a_date_field_and_the_service_dates(): void
    {
        $type = ContentType::create(['key' => 'events', 'name' => 'Events']);
        ContentField::create(['content_type' => 'events', 'key' => 'date', 'name' => 'Date', 'type' => 'datetime']);
        ContentField::create(['content_type' => 'events', 'key' => 'price', 'name' => 'Price', 'type' => 'integer']);

        foreach (['date', 'created_at', 'unpublished_at'] as $field) {
            $type->update(['settings' => ['public' => ['archive' => ['field' => $field, 'sort' => ['field' => 'date', 'direction' => 'desc']]]]]);
            $this->assertSame($field, $type->fresh()->settings['public']['archive']['field']);
        }
    }

    public function test_the_public_archive_rejects_a_bad_field_sort_or_keys(): void
    {
        $type = ContentType::create(['key' => 'events', 'name' => 'Events']);
        ContentField::create(['content_type' => 'events', 'key' => 'date', 'name' => 'Date', 'type' => 'datetime']);
        ContentField::create(['content_type' => 'events', 'key' => 'price', 'name' => 'Price', 'type' => 'integer']);

        $this->assertArchiveInvalid($type, ['field' => 'price']);
        $this->assertArchiveInvalid($type, ['field' => 'missing']);
        $this->assertArchiveInvalid($type, ['field' => 'published_at']);
        $this->assertArchiveInvalid($type, ['sort' => ['field' => 'date']]);
        $this->assertArchiveInvalid($type, ['field' => 'date', 'sort' => ['field' => 'missing']]);
        $this->assertArchiveInvalid($type, ['field' => 'date', 'sort' => ['field' => 'date', 'direction' => 'up']]);
        $this->assertArchiveInvalid($type, ['field' => 'date', 'extra' => 1]);
        $this->assertNull($type->fresh()->settings);
    }

    public function test_the_public_archive_is_for_the_content_kind_only(): void
    {
        foreach (['page', 'block'] as $kind) {
            $type = ContentType::create(['key' => $kind.'_type', 'name' => 'T', 'kind' => $kind]);

            $this->assertArchiveInvalid($type, ['field' => 'created_at']);
        }
    }

    /** @param  array<string, mixed>  $sitemap */
    private function assertSitemapInvalid(string $kind, array $sitemap): void
    {
        $type = ContentType::create(['key' => 'sm_'.$kind.'_'.md5(json_encode($sitemap)), 'name' => 'X', 'kind' => $kind]);

        try {
            $type->update(['settings' => ['sitemap' => $sitemap]]);
            $this->fail('The sitemap settings should be rejected: '.json_encode($sitemap));
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('settings', $e->errors());
        }
    }

    public function test_the_sitemap_settings_accept_the_documented_shape(): void
    {
        $content = ContentType::create(['key' => 'news', 'name' => 'News', 'slug' => 'news']);
        $sitemap = ['xml' => ['type' => true, 'nodes' => true, 'changefreq' => 'monthly', 'priority' => '0.8'], 'web' => ['type' => true]];
        $content->update(['settings' => ['sitemap' => $sitemap]]);
        $this->assertSame($sitemap, $content->fresh()->settings['sitemap']);

        $page = ContentType::create(['key' => 'about', 'name' => 'About', 'kind' => 'page']);
        $page->update(['settings' => ['sitemap' => ['xml' => ['nodes' => true, 'changefreq' => 'never', 'priority' => '1.0'], 'web' => ['nodes' => true]]]]);
        $this->assertTrue($page->fresh()->settings['sitemap']['web']['nodes']);
    }

    public function test_the_sitemap_settings_reject_bad_values(): void
    {
        $this->assertSitemapInvalid('content', ['xml' => ['nodes' => true, 'changefreq' => 'sometimes']]);
        $this->assertSitemapInvalid('content', ['xml' => ['nodes' => true, 'priority' => '1.5']]);
        $this->assertSitemapInvalid('content', ['xml' => ['nodes' => true, 'priority' => '0.55']]);
        $this->assertSitemapInvalid('content', ['xml' => ['nodes' => 'yes']]);
        $this->assertSitemapInvalid('content', ['rss' => ['nodes' => true]]);
        $this->assertSitemapInvalid('content', ['web' => ['nodes' => true, 'changefreq' => 'daily']]);
        $this->assertSitemapInvalid('content', ['xml' => ['changefreq' => 'daily']]);
        $this->assertSitemapInvalid('content', ['xml' => ['nodes' => false, 'priority' => '0.5']]);
    }

    public function test_a_page_type_has_no_sitemap_list_page_and_a_block_has_no_sitemap(): void
    {
        $this->assertSitemapInvalid('page', ['xml' => ['type' => true]]);
        $this->assertSitemapInvalid('page', ['web' => ['type' => false]]);
        $this->assertSitemapInvalid('block', ['web' => ['nodes' => true]]);
    }
}
