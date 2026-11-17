<?php

namespace Webtack\Ecms\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Webtack\Ecms\Content\ContentNodeCreator;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class ReferenceLookupTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        ContentType::create(['key' => 'hero', 'name' => 'Hero banner', 'kind' => 'block']);
        ContentType::create(['key' => 'gallery', 'name' => 'Gallery', 'kind' => 'block']);
        ContentType::create(['key' => 'home', 'name' => 'Home hero', 'kind' => 'page']);
        ContentType::create(['key' => 'article', 'name' => 'Article']);
    }

    private function node(string $type, string $title): string
    {
        return app(ContentNodeCreator::class)->create(ContentType::findByKey($type), ['title' => $title], authorId: $this->admin->id)->uuid;
    }

    public function test_types_are_searched_by_name_within_the_kind(): void
    {
        $this->actingAs($this->admin)->getJson('/admin/lookup/content-types?kind=block&q=hero')
            ->assertOk()
            ->assertExactJson([['key' => 'hero', 'name' => 'Hero banner']]);
    }

    public function test_an_empty_query_gives_the_types_of_the_kind_in_name_order(): void
    {
        $this->actingAs($this->admin)->getJson('/admin/lookup/content-types?kind=block')
            ->assertOk()
            ->assertExactJson([['key' => 'gallery', 'name' => 'Gallery'], ['key' => 'hero', 'name' => 'Hero banner']]);
    }

    public function test_wildcards_of_the_query_are_taken_literally(): void
    {
        $this->actingAs($this->admin)->getJson('/admin/lookup/content-types?kind=block&q=%25')
            ->assertOk()->assertExactJson([]);
    }

    public function test_the_types_are_limited_to_twenty(): void
    {
        foreach (range(1, 25) as $i) {
            ContentType::create(['key' => "block_{$i}", 'name' => sprintf('Block %02d', $i), 'kind' => 'block']);
        }

        $this->actingAs($this->admin)->getJson('/admin/lookup/content-types?kind=block&q=Block')
            ->assertOk()->assertJsonCount(20)->assertJsonPath('0.name', 'Block 01');
    }

    public function test_nodes_are_searched_by_title_within_the_type(): void
    {
        $news = $this->node('article', 'Latest news');
        $this->node('article', 'About us');
        $this->node('hero', 'News hero');

        $this->actingAs($this->admin)->getJson('/admin/lookup/nodes?content_type=article&q=news')
            ->assertOk()
            ->assertExactJson([['uuid' => $news, 'title' => 'Latest news']]);
    }

    public function test_an_empty_query_gives_the_first_nodes_by_title(): void
    {
        $this->node('article', 'Beta');
        $alpha = $this->node('article', 'Alpha');

        $response = $this->actingAs($this->admin)->getJson('/admin/lookup/nodes?content_type=article')->assertOk();

        $this->assertSame(['Alpha', 'Beta'], array_column($response->json(), 'title'));
        $this->assertSame($alpha, $response->json('0.uuid'));
    }

    public function test_the_nodes_are_limited_to_twenty(): void
    {
        foreach (range(1, 25) as $i) {
            $this->node('article', sprintf('Item %02d', $i));
        }

        $this->actingAs($this->admin)->getJson('/admin/lookup/nodes?content_type=article')
            ->assertOk()->assertJsonCount(20);
    }

    public function test_an_unknown_kind_or_type_is_a_validation_error(): void
    {
        $this->actingAs($this->admin)->getJson('/admin/lookup/content-types?kind=nope')->assertStatus(422)->assertJsonValidationErrors('kind');
        $this->actingAs($this->admin)->getJson('/admin/lookup/content-types')->assertStatus(422)->assertJsonValidationErrors('kind');
        $this->actingAs($this->admin)->getJson('/admin/lookup/nodes?content_type=nope')->assertStatus(422)->assertJsonValidationErrors('content_type');
        $this->actingAs($this->admin)->getJson('/admin/lookup/nodes')->assertStatus(422)->assertJsonValidationErrors('content_type');
    }

    public function test_a_guest_is_refused(): void
    {
        $this->getJson('/admin/lookup/content-types?kind=block')->assertUnauthorized();
        $this->getJson('/admin/lookup/nodes?content_type=article')->assertUnauthorized();
    }
}
