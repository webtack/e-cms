<?php

namespace Webtack\Ecms\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Webtack\Ecms\Content\ContentNodeCreator;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class ContentFieldDestroyTest extends TestCase
{
    /** System fields a `content` type has besides `title`. */
    private const SYSTEM_KEYS = ['meta_title', 'meta_description', 'meta_image', 'section_title', 'section_description', 'section_cover'];

    use RefreshDatabase;

    private function admin(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    private function field(string $key, string $type = 'integer', int $weight = 0): ContentField
    {
        return ContentField::create(['content_type' => 'news', 'key' => $key, 'name' => ucfirst($key), 'type' => $type, 'is_required' => false, 'weight' => $weight]);
    }

    /**
     * @param  list<string>  $order
     */
    private function weights(array $order): array
    {
        return collect($order)->mapWithKeys(fn (string $key) => [$key => ContentField::where('content_type', 'news')->where('key', $key)->value('weight')])->all();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);
        $this->field('visits');

        $this->delete('/admin/content-types/news/fields/visits')->assertRedirect('/login');
        $this->put('/admin/content-types/news/fields/order', ['order' => ['visits', 'title']])->assertRedirect('/login');

        $this->assertNotNull(ContentField::where('key', 'visits')->first());
    }

    public function test_an_ordinary_field_is_deleted(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);
        $this->field('visits');

        $this->admin()->delete('/admin/content-types/news/fields/visits')
            ->assertRedirect('/admin/content-types/news/fields')
            ->assertSessionHas('success');

        $this->assertNull(ContentField::where('key', 'visits')->first());
    }

    public function test_a_field_with_values_is_kept_with_its_values(): void
    {
        $type = ContentType::create(['key' => 'news', 'name' => 'News']);
        $this->field('visits');
        app(ContentNodeCreator::class)->create($type, ['title' => 'Hello', 'visits' => 5], authorId: $this->authorId());

        $this->admin()->from('/admin/content-types/news/fields')->delete('/admin/content-types/news/fields/visits')
            ->assertRedirect('/admin/content-types/news/fields')
            ->assertSessionHas('error')
            ->assertSessionMissing('success');

        $this->assertNotNull(ContentField::where('key', 'visits')->first());
        $this->assertSame(1, DB::table('content_values_integer')->where('field_key', 'visits')->count());
    }

    public function test_the_title_field_is_kept(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);

        $this->admin()->from('/admin/content-types/news/fields')->delete('/admin/content-types/news/fields/title')
            ->assertRedirect('/admin/content-types/news/fields')
            ->assertSessionHas('error');

        $this->assertNotNull(ContentField::where('key', 'title')->first());
    }

    public function test_the_order_sets_weights_from_zero(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);
        $this->field('summary', 'text', 10);
        $this->field('body', 'text', 20);
        ContentType::create(['key' => 'other', 'name' => 'Other']);

        $this->admin()->from('/admin/content-types/news/fields')
            ->put('/admin/content-types/news/fields/order', ['order' => ['body', 'title', 'summary']])
            ->assertRedirect('/admin/content-types/news/fields')
            ->assertSessionHas('success')
            ->assertSessionHasNoErrors();

        $hidden = $this->weights(self::SYSTEM_KEYS);
        $this->assertSame(['body' => 0, 'title' => 1, 'summary' => 2], $this->weights(['body', 'title', 'summary']));
        // The weights of panel fields are not touched.
        $this->assertSame($hidden, $this->weights(self::SYSTEM_KEYS));
        $this->assertSame(0, ContentField::where('content_type', 'other')->where('key', 'title')->value('weight'));
    }

    public function test_an_incomplete_repeated_or_foreign_order_is_rejected(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);
        $this->field('summary', 'text', 10);
        ContentType::create(['key' => 'other', 'name' => 'Other']);
        $this->field('visits')->forceFill(['content_type' => 'other'])->saveQuietly();

        foreach ([
            ['title'],
            ['title', 'summary', 'visits'],
            [],
            'title',
        ] as $order) {
            $this->admin()->put('/admin/content-types/news/fields/order', ['order' => $order])->assertSessionHasErrors('order');
        }

        // A repeated key fails the per-element `distinct` rule, so the error sits under that element.
        $this->admin()->put('/admin/content-types/news/fields/order', ['order' => ['title', 'summary', 'summary']])
            ->assertSessionHasErrors('order.1');

        $this->assertSame(['title' => 0, 'summary' => 10], $this->weights(['title', 'summary']));
    }

    public function test_the_order_is_rejected_when_it_lists_a_panel_field_or_lacks_a_shown_one(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);
        $this->field('summary', 'text', 10);
        $before = $this->weights(['title', 'summary', 'meta_title']);

        foreach ([['title', 'summary', 'meta_title'], ['title', 'summary', ...self::SYSTEM_KEYS], ['summary']] as $order) {
            $this->admin()->put('/admin/content-types/news/fields/order', ['order' => $order])->assertSessionHasErrors('order');
        }

        $this->assertSame($before, $this->weights(['title', 'summary', 'meta_title']));
    }

    public function test_deleting_a_panel_field_is_not_found(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);

        foreach (self::SYSTEM_KEYS as $key) {
            $this->admin()->delete("/admin/content-types/news/fields/{$key}")->assertNotFound();
            $this->assertNotNull(ContentField::where('content_type', 'news')->where('key', $key)->first());
        }
    }

    public function test_the_order_of_a_non_content_type_is_not_found(): void
    {
        ContentType::create(['key' => 'home', 'name' => 'Home', 'kind' => 'page']);

        $this->admin()->put('/admin/content-types/home/fields/order', ['order' => ['title']])->assertNotFound();
        $this->admin()->put('/admin/content-types/missing/fields/order', ['order' => ['title']])->assertNotFound();
    }
}
