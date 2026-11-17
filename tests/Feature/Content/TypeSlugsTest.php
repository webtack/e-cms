<?php

namespace Webtack\Ecms\Tests\Feature\Content;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Webtack\Ecms\Content\TypeSlugs;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Tests\TestCase;

class TypeSlugsTest extends TestCase
{
    use RefreshDatabase;

    public function test_prefix_check_is_case_insensitive_and_by_whole_segments(): void
    {
        $slugs = app(TypeSlugs::class);

        $this->assertTrue($slugs->startsWithParent('common/institutions', 'common'));
        $this->assertTrue($slugs->startsWithParent('Common/City/Awards', 'common'));
        $this->assertFalse($slugs->startsWithParent('common', 'common'));
        $this->assertFalse($slugs->startsWithParent('common/', 'common'));
        $this->assertFalse($slugs->startsWithParent('commonplace/x', 'common'));
        $this->assertFalse($slugs->startsWithParent('other/institutions', 'common'));
    }

    public function test_hierarchy_error_needs_a_parent_with_a_slug_and_the_prefix(): void
    {
        $slugs = app(TypeSlugs::class);
        ContentType::create(['key' => 'common', 'name' => 'Common', 'slug' => 'common']);
        ContentType::create(['key' => 'bare', 'name' => 'Bare']);

        $this->assertNull($slugs->hierarchyError('common/institutions', 'common'));
        $this->assertNull($slugs->hierarchyError(null, 'common'));
        $this->assertNull($slugs->hierarchyError('anything', null));
        $this->assertStringContainsString('common/', $slugs->hierarchyError('institutions', 'common'));
        $this->assertSame(__('The parent type must have a slug first.'), $slugs->hierarchyError('a/b', 'bare'));
    }

    public function test_required_prefix_and_direct_children_with_a_slug(): void
    {
        $slugs = app(TypeSlugs::class);
        $common = ContentType::create(['key' => 'common', 'name' => 'Common', 'slug' => 'common']);
        $bare = ContentType::create(['key' => 'bare', 'name' => 'Bare']);

        $this->assertSame('common/', $slugs->requiredPrefix('common'));
        $this->assertNull($slugs->requiredPrefix('bare'));

        ContentType::create(['key' => 'no_slug_child', 'name' => 'C', 'parent_type' => 'common']);
        $this->assertFalse($slugs->hasChildWithSlug($common));
        $this->assertNull($slugs->childrenBlockSlugChange($common));

        ContentType::create(['key' => 'inst', 'name' => 'I', 'parent_type' => 'common', 'slug' => 'common/institutions']);
        $this->assertTrue($slugs->hasChildWithSlug($common));
        $this->assertNotNull($slugs->childrenBlockSlugChange($common));
        $this->assertFalse($slugs->hasChildWithSlug($bare));
    }
}
