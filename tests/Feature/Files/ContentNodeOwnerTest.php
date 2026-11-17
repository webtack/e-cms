<?php

namespace Webtack\Ecms\Tests\Feature\Files;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Webtack\Ecms\Files\Owners\ContentNodeOwner;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Tests\Feature\Content\Concerns\BuildsArticleType;
use Webtack\Ecms\Tests\TestCase;

class ContentNodeOwnerTest extends TestCase
{
    use BuildsArticleType, RefreshDatabase;

    public function test_the_owner_type_is_registered(): void
    {
        $this->assertSame(ContentNodeOwner::class, config('files.owners.content_node'));
    }

    public function test_a_node_gets_its_title_and_edit_link(): void
    {
        $node = $this->makeArticle(['title' => 'Привіт']);

        $described = (new ContentNodeOwner)->describe([$node->id]);

        $this->assertSame([
            $node->id => ['label' => 'Привіт', 'url' => "/admin/content-types/article/nodes/{$node->uuid}/edit"],
        ], $described);
    }

    public function test_page_and_block_nodes_link_to_their_own_section(): void
    {
        ContentType::create(['key' => 'home', 'name' => 'Home', 'kind' => 'page']);
        ContentType::create(['key' => 'hero', 'name' => 'Hero', 'kind' => 'block']);
        $page = ContentNode::factory()->create(['content_type' => 'home', 'slug' => 'home']);
        $block = ContentNode::factory()->create(['content_type' => 'hero', 'slug' => 'block-hero']);

        $this->assertSame([
            $page->id => ['label' => 'home', 'url' => "/admin/page-types/home/nodes/{$page->uuid}/edit"],
            $block->id => ['label' => 'block-hero', 'url' => "/admin/block-types/hero/nodes/{$block->uuid}/edit"],
        ], (new ContentNodeOwner)->describe([$page->id, $block->id]));
    }

    public function test_a_missing_node_is_absent_from_the_result(): void
    {
        $node = $this->makeArticle();

        $this->assertSame([$node->id], array_keys((new ContentNodeOwner)->describe([$node->id, 9999])));
        $this->assertSame([], (new ContentNodeOwner)->describe([]));
    }

    public function test_many_owners_are_described_in_a_fixed_number_of_queries(): void
    {
        $ids = collect(range(1, 5))->map(fn (int $i) => $this->makeArticle(['title' => "Node {$i}"], "node-{$i}")->id)->all();

        DB::enableQueryLog();
        $described = (new ContentNodeOwner)->describe($ids);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(5, $described);
        $this->assertSame(2, $queries);
    }
}
