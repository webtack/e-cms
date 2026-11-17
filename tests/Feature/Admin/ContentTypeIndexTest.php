<?php

namespace Webtack\Ecms\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class ContentTypeIndexTest extends TestCase
{
    use RefreshDatabase;

    private function visit(string $query = '')
    {
        return $this->actingAs(User::factory()->create())->get('/admin/content-types'.$query);
    }

    private function type(string $key, string $name, string $kind = 'content', ?string $description = null, ?string $parent = null, ?string $slug = null): ContentType
    {
        return ContentType::create([
            'key' => $key,
            'name' => $name,
            'kind' => $kind,
            'description' => $description,
            'parent_type' => $parent,
            'slug' => $slug,
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/content-types')->assertRedirect('/login');
    }

    public function test_the_route_has_its_name(): void
    {
        $this->assertSame('/admin/content-types', route('admin.content-types.index', absolute: false));
    }

    public function test_it_renders_the_page_with_the_table_prop(): void
    {
        $this->type('article', 'Article');

        $this->visit()
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/ContentTypes/Index')
                ->has('table.rows', 1)
                ->where('table.meta.total', 1)
                ->where('table.ignored', [])
                ->where('table.rows.0.key', 'article')
                ->where('table.rows.0.slug', null)
                ->where('table.rows.0.parent_type', null)
                ->missing('table.rows.0.kind'));
    }

    public function test_only_content_types_are_listed(): void
    {
        $this->type('article', 'Article');
        $this->type('home', 'Home', 'page');
        $this->type('hero', 'Hero', 'block');

        $this->visit()
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.meta.total', 1)
                ->where('table.rows.0.key', 'article'));
    }

    public function test_rows_carry_the_slug(): void
    {
        $this->type('news', 'News', slug: 'latest-news');
        $this->type('article', 'Article');

        $this->visit('?sort=key&dir=asc')
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.rows.0.key', 'article')
                ->where('table.rows.0.slug', null)
                ->where('table.rows.1.slug', 'latest-news'));

        $this->visit('?sort=slug&dir=asc')
            ->assertInertia(fn (Assert $page) => $page->where('table.rows.0.key', 'news'));
    }

    public function test_rows_carry_the_child_type_count(): void
    {
        $this->type('article', 'Article');
        $this->type('news', 'News', parent: 'article');
        $this->type('sport', 'Sport', parent: 'article');

        $this->visit('?sort=key&dir=asc')
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.rows.0.key', 'article')
                ->where('table.rows.0.children_count', 2)
                ->where('table.rows.1.children_count', 0));
    }

    public function test_it_counts_fields_with_the_system_title_and_nodes(): void
    {
        $article = $this->type('article', 'Article');
        ContentField::factory()->create(['content_type' => 'article', 'key' => 'body']);
        ContentNode::factory()->count(5)->create(['content_type' => 'article']);
        $this->type('empty', 'Empty');

        $this->visit()
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.rows.0.key', 'article')
                ->where('table.rows.0.fields_count', 8)
                ->where('table.rows.0.nodes_count', 5)
                ->where('table.rows.1.fields_count', 7)
                ->where('table.rows.1.nodes_count', 0));

        $this->assertSame(8, $article->fields()->count());
    }

    public function test_rows_are_ordered_by_name_by_default_and_a_sort_overrides_it(): void
    {
        $this->type('b_type', 'banana');
        $this->type('a_type', 'Cherry');
        $this->type('c_type', 'Apple');

        $this->visit()
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.rows', fn ($rows) => collect($rows)->pluck('name')->all() === ['Apple', 'banana', 'Cherry']));

        $this->visit('?sort=key&dir=asc')
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.rows', fn ($rows) => collect($rows)->pluck('key')->all() === ['a_type', 'b_type', 'c_type']));
    }

    public function test_search_matches_name_description_slug_and_key(): void
    {
        $this->type('article', 'Article');
        $this->type('invoice', 'Billing', description: 'Holds a SPECIMEN reference');
        $this->type('product', 'Product', slug: 'shop-items');
        $this->type('misc', 'Misc');

        $this->visit('?q=article')
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.meta.total', 1)
                ->where('table.rows.0.key', 'article')
                ->where('table.query.search', 'article'));

        $this->visit('?q=specimen')
            ->assertInertia(fn (Assert $page) => $page->where('table.meta.total', 1)->where('table.rows.0.key', 'invoice'));

        $this->visit('?q=shop')
            ->assertInertia(fn (Assert $page) => $page->where('table.meta.total', 1)->where('table.rows.0.key', 'product'));

        $this->visit('?q=prod')
            ->assertInertia(fn (Assert $page) => $page->where('table.meta.total', 1)->where('table.rows.0.key', 'product'));
    }

    public function test_search_does_not_match_the_parent_type(): void
    {
        $this->type('article', 'Article');
        $this->type('news', 'News', parent: 'article');

        $this->visit('?q=article')
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.meta.total', 1)
                ->where('table.rows.0.key', 'article'));
    }

    public function test_filters_in_the_address_are_ignored(): void
    {
        $this->type('article', 'Article', slug: 'articles');
        $this->type('news', 'News');
        ContentNode::factory()->count(2)->create(['content_type' => 'article']);
        $this->type('home', 'Home', 'page');

        foreach ([
            '?filters[0][c]=kind&filters[0][o]=eq&filters[0][v]=page',
            '?filters[0][c]=nodes_count&filters[0][o]=gte&filters[0][v]=1',
            '?filters[0][c]=slug&filters[0][o]=eq&filters[0][v]=articles',
        ] as $query) {
            $this->visit($query)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('table.meta.total', 2)
                    ->where('table.query.filters', [])
                    ->where('table.ignored', fn ($ignored) => count($ignored) === 1));
        }
    }

    public function test_sort_by_node_count(): void
    {
        $this->type('article', 'Article');
        $this->type('news', 'News');
        $this->type('empty', 'Empty');
        ContentNode::factory()->count(2)->create(['content_type' => 'article']);
        ContentNode::factory()->count(4)->create(['content_type' => 'news']);

        $this->visit('?sort=nodes_count&dir=desc')
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.rows', fn ($rows) => collect($rows)->pluck('key')->all() === ['news', 'article', 'empty']));
    }

    public function test_the_parent_type_is_shown(): void
    {
        $this->type('article', 'Article');
        $this->type('news', 'News', parent: 'article');

        $this->visit('?sort=key&dir=desc')
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.rows.0.key', 'news')
                ->where('table.rows.0.parent_type', 'article')
                ->where('table.rows.0.parent_name', 'Article')
                ->where('table.rows.1.parent_type', null)
                ->where('table.rows.1.parent_name', null));
    }

    public function test_the_parent_column_sorts_by_the_parent_name(): void
    {
        $this->type('zeta', 'Alpha');
        $this->type('alpha', 'Zulu');
        $this->type('one', 'One', parent: 'zeta');
        $this->type('two', 'Two', parent: 'alpha');

        $this->visit('?sort=parent_name&dir=asc')
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.ignored', [])
                ->where('table.rows', function ($rows) {
                    $named = collect($rows)->whereNotNull('parent_name')->pluck('key')->values()->all();

                    return $named === ['one', 'two'];
                }));
    }

    public function test_the_child_types_column_is_sortable_and_only_for_those_who_manage_types(): void
    {
        $this->type('article', 'Article');
        $this->type('news', 'News', parent: 'article');
        $this->type('sport', 'Sport', parent: 'article');

        $this->visit('?sort=children_count&dir=desc')
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.ignored', [])
                ->where('table.rows.0.key', 'article'));

        $user = User::factory()->withRole(Role::factory()->withPermissions(['node:news' => 'any'])->create())->create();

        $this->actingAs($user)->get('/admin/content-types?sort=children_count')
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.ignored', fn ($ignored) => count($ignored) >= 1)
                ->where('table.rows.0.parent_name', 'Article'));
    }

    public function test_pagination(): void
    {
        foreach (range(1, 20) as $i) {
            $this->type(sprintf('type_%02d', $i), sprintf('Type %02d', $i));
        }

        $this->visit('?page=2')
            ->assertInertia(fn (Assert $page) => $page
                ->has('table.rows', 5)
                ->where('table.meta.total', 20)
                ->where('table.meta.lastPage', 2)
                ->where('table.rows.0.key', 'type_16'));
    }

    public function test_invalid_parameters_are_reported_not_failed(): void
    {
        $this->type('article', 'Article');

        $this->visit('?sort=nope&filters[0][c]=zzz&filters[0][o]=eq&filters[0][v]=1')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.meta.total', 1)
                ->where('table.ignored', fn ($ignored) => count($ignored) >= 1));
    }

    public function test_it_renders_an_empty_table_without_types(): void
    {
        $this->visit()
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('table.rows', 0)
                ->where('table.meta.total', 0));
    }
}
