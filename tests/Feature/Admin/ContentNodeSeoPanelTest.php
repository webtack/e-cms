<?php

namespace Webtack\Ecms\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Content\ContentNodeCreator;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

/**
 * Which fields the node form offers: metadata in the `seo` panel, section fields never.
 */
class ContentNodeSeoPanelTest extends TestCase
{
    use RefreshDatabase;

    private const SECTIONS = ['content' => 'content-types', 'page' => 'page-types', 'block' => 'block-types'];

    private function admin(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    /** @return array<string, string|null> key => panel of the form fields */
    private function panels(string $kind): array
    {
        ContentType::create(['key' => 't', 'name' => 'T', 'kind' => $kind]);
        $panels = [];

        $this->admin()->get('/admin/'.self::SECTIONS[$kind].'/t/nodes/create')
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$panels) {
                $page->where('fields', function ($fields) use (&$panels) {
                    $panels = collect($fields)->mapWithKeys(fn ($f) => [$f['key'] => $f['panel'] ?? null])->all();

                    return true;
                });
            });

        return $panels;
    }

    public function test_a_content_type_form_has_title_and_the_seo_fields_without_section_fields(): void
    {
        $this->assertSame(
            ['title' => null, 'meta_title' => 'seo', 'meta_description' => 'seo', 'meta_image' => 'seo'],
            $this->panels('content'),
        );
    }

    public function test_a_page_type_form_has_the_seo_fields(): void
    {
        $this->assertSame(
            ['title' => null, 'meta_title' => 'seo', 'meta_description' => 'seo', 'meta_image' => 'seo'],
            $this->panels('page'),
        );
    }

    public function test_a_block_type_form_has_only_the_title(): void
    {
        $this->assertSame(['title' => null], $this->panels('block'));
    }

    public function test_the_edit_form_carries_the_stored_seo_values_and_no_section_values(): void
    {
        $type = ContentType::create(['key' => 'news', 'name' => 'News']);
        $node = app(ContentNodeCreator::class)->create($type, ['title' => 'Hello', 'meta_title' => 'SEO', 'meta_description' => 'About'], authorId: $this->authorId());

        $this->admin()->get("/admin/content-types/news/nodes/{$node->uuid}/edit")
            ->assertInertia(fn (Assert $page) => $page
                ->where('values.meta_title', 'SEO')
                ->where('values.meta_description', 'About')
                ->where('values.meta_image', null)
                ->missing('values.section_title'));
    }

    public function test_the_seo_values_are_saved_with_the_node_and_section_values_are_refused(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);

        $this->admin()->post('/admin/content-types/news/nodes', ['slug' => 'a', 'values' => ['title' => 'A', 'meta_title' => 'SEO']])
            ->assertSessionHasNoErrors();
        $this->admin()->post('/admin/content-types/news/nodes', ['slug' => 'b', 'values' => ['title' => 'B', 'section_title' => 'X']])
            ->assertSessionHasErrors('values.section_title');
    }
}
