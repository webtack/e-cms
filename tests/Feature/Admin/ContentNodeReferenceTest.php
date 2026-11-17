<?php

namespace Webtack\Ecms\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Content\ContentNodeCreator;
use Webtack\Ecms\Content\ContentValueWriter;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class ContentNodeReferenceTest extends TestCase
{
    use RefreshDatabase;

    private ContentNode $target;

    private ContentNode $article;

    protected function setUp(): void
    {
        parent::setUp();

        ContentType::create(['key' => 'article', 'name' => 'Article']);
        ContentType::create(['key' => 'hero', 'name' => 'Hero banner', 'kind' => 'block']);
        ContentType::create(['key' => 'news', 'name' => 'News']);
        foreach ([
            ['source', 'type_ref', ['kind' => 'block'], false],
            ['related', 'node_ref', ['content_type' => 'news'], false],
            ['all_related', 'node_ref', ['content_type' => 'news'], true],
        ] as [$key, $type, $settings, $multiple]) {
            ContentField::create(['content_type' => 'article', 'key' => $key, 'name' => ucfirst($key), 'type' => $type, 'settings' => $settings, 'is_multiple' => $multiple, 'weight' => 10]);
        }

        $this->target = $this->node('news', ['title' => 'Latest news']);
        $this->article = $this->node('article', ['title' => 'Post']);
        app(ContentValueWriter::class)->write($this->article, [
            'source' => 'hero',
            'related' => $this->target->uuid,
            'all_related' => [$this->target->uuid],
        ]);
        ContentType::findByKey('article')->update(['settings' => ['list' => ['visible' => ['title', 'source', 'related', 'all_related']]]]);
    }

    private function admin(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    /** @param array<string, mixed> $values */
    private function node(string $type, array $values): ContentNode
    {
        return app(ContentNodeCreator::class)->create(ContentType::findByKey($type), $values, authorId: $this->authorId());
    }

    public function test_the_list_shows_the_label_and_edit_address_of_each_target(): void
    {
        $this->admin()->get('/admin/content-types/article/nodes')->assertInertia(fn (Assert $page) => $page
            ->where('table.rows.0.source', ['label' => 'Hero banner', 'href' => '/admin/block-types/hero/edit'])
            ->where('table.rows.0.related', ['label' => 'Latest news', 'href' => "/admin/content-types/news/nodes/{$this->target->uuid}/edit"])
            ->where('table.rows.0.all_related', [['label' => 'Latest news', 'href' => "/admin/content-types/news/nodes/{$this->target->uuid}/edit"]])
            ->where('columns', fn ($columns) => collect($columns)->firstWhere('key', 'related')['sortable'] === true
                && collect($columns)->firstWhere('key', 'source')['type'] === 'text'));
    }

    public function test_a_gone_target_shows_the_stored_value(): void
    {
        DB::table('content_nodes')->where('id', $this->target->id)->delete();

        $this->admin()->get('/admin/content-types/article/nodes')->assertInertia(fn (Assert $page) => $page
            ->where('table.rows.0.related', $this->target->uuid));
    }

    public function test_the_list_sorts_by_the_label_of_the_target(): void
    {
        $second = $this->node('article', ['title' => 'Second']);
        $alpha = $this->node('news', ['title' => 'Alpha']);
        app(ContentValueWriter::class)->write($second, ['related' => $alpha->uuid]);

        $this->admin()->get('/admin/content-types/article/nodes?sort=related&dir=asc')->assertInertia(fn (Assert $page) => $page
            ->where('table.rows.0.title', 'Second')
            ->where('table.rows.1.title', 'Post'));

        $this->admin()->get('/admin/content-types/article/nodes?sort=related&dir=desc')->assertInertia(fn (Assert $page) => $page
            ->where('table.rows.0.title', 'Post'));
    }

    public function test_the_edit_form_gets_the_labels_of_the_stored_values(): void
    {
        $this->admin()->get("/admin/content-types/article/nodes/{$this->article->uuid}/edit")->assertInertia(fn (Assert $page) => $page
            ->where('labels', ['hero' => 'Hero banner', $this->target->uuid => 'Latest news'])
            ->where('values.source', 'hero')
            ->where('values.related', $this->target->uuid));
    }

    public function test_a_referenced_node_is_not_deleted_and_the_message_names_the_place(): void
    {
        $this->admin()->from('/admin/content-types/news/nodes')->delete("/admin/content-types/news/nodes/{$this->target->uuid}")
            ->assertRedirect('/admin/content-types/news/nodes')
            ->assertSessionHas('error');

        $this->assertModelExists($this->target);
        $this->assertStringContainsString('related (article)', session('error'));
    }

    public function test_a_referenced_type_is_not_deleted_from_the_admin(): void
    {
        $this->admin()->from('/admin/block-types')->delete('/admin/block-types/hero')
            ->assertRedirect('/admin/block-types')
            ->assertSessionHas('error');

        $this->assertNotNull(ContentType::findByKey('hero'));
    }

    public function test_an_unreferenced_node_is_deleted(): void
    {
        $free = $this->node('news', ['title' => 'Free']);

        $this->admin()->delete("/admin/content-types/news/nodes/{$free->uuid}")->assertSessionHas('success');

        $this->assertModelMissing($free);
    }
}
