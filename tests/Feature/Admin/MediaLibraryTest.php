<?php

namespace Webtack\Ecms\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Content\ContentLanguages;
use Webtack\Ecms\Content\ContentNodeCreator;
use Webtack\Ecms\Files\FileDescriptions;
use Webtack\Ecms\Files\FileRelations;
use Webtack\Ecms\Models\AppLanguage;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\File;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class MediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    private string $lastUuid = '';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function admin(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    /** @param array<string, mixed> $attributes */
    private function file(array $attributes = [], bool $onDisk = true): File
    {
        $uuid = File::newUuid();
        $file = File::factory()->create(['uuid' => $uuid, 'path' => "{$uuid}.pdf", ...$attributes]);

        if ($onDisk) {
            Storage::disk('public')->put($file->path, 'x');
        }

        return $file;
    }

    private function use(File $file, int $ownerId = 1, string $role = 'cover', string $type = 'content_node', string $lang = 'default'): void
    {
        DB::table('media_relations')->insert([
            'file_uuid' => $file->uuid, 'owner_type' => $type, 'owner_id' => $ownerId, 'role' => $role, 'lang' => $lang,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // --- index ---

    public function test_a_guest_is_redirected_to_login(): void
    {
        $file = $this->file();

        $this->get('/admin/media')->assertRedirect('/login');
        $this->getJson("/admin/media/{$file->uuid}")->assertUnauthorized();
        $this->delete("/admin/media/{$file->uuid}")->assertRedirect('/login');
        $this->assertDatabaseHas('media', ['uuid' => $file->uuid]);
    }

    public function test_an_unverified_user_is_refused(): void
    {
        $this->actingAs(User::factory()->unverified()->create())->get('/admin/media')->assertRedirect(route('verification.notice'));
    }

    public function test_every_file_is_listed_whoever_uploaded_it_with_its_use_count(): void
    {
        [$ann, $bob] = User::factory()->count(2)->create();
        $used = $this->file(['user_id' => $ann->id, 'original_name' => 'used.pdf']);
        $unused = $this->file(['user_id' => $bob->id, 'original_name' => 'unused.pdf']);
        $twice = $this->file(['user_id' => null, 'original_name' => 'twice.pdf']);
        $this->use($used);
        $this->use($twice, 1);
        $this->use($twice, 2);

        $this->actingAs($ann)->get('/admin/media?sort=name&dir=asc')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Media/Index')
                ->has('table.rows', 3)
                ->where('table.rows.0.name', 'twice.pdf')
                ->where('table.rows.0.uses', 2)
                ->where('table.rows.0.uploader', null)
                ->where('table.rows.1.name', 'unused.pdf')
                ->where('table.rows.1.uses', 0)
                ->where('table.rows.1.uploader', $bob->name)
                ->where('table.rows.2.name', 'used.pdf')
                ->where('table.rows.2.uses', 1)
                ->where('table.rows.2.uploader', $ann->name)
                ->where('table.meta.total', 3));
    }

    public function test_rows_carry_the_uploader_avatar_url_or_null(): void
    {
        $withAvatar = User::factory()->create(['avatar' => '0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.jpg']);
        $plain = User::factory()->create();
        $this->file(['user_id' => $withAvatar->id, 'original_name' => 'a.pdf']);
        $this->file(['user_id' => $plain->id, 'original_name' => 'b.pdf']);
        $this->file(['user_id' => null, 'original_name' => 'c.pdf']);

        $this->admin()->get('/admin/media?sort=name&dir=asc')->assertInertia(fn (Assert $page) => $page
            ->where('table.rows.0.uploader_avatar', Storage::disk('public')->url('0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.jpg'))
            ->where('table.rows.1.uploader_avatar', null)
            ->where('table.rows.2.uploader_avatar', null));
    }

    public function test_the_user_owner_is_described_by_the_user_name(): void
    {
        $user = User::factory()->create(['name' => 'Oksana']);
        $file = $this->file();
        $this->use($file, $user->id, 'avatar', 'user');

        $this->admin()->getJson("/admin/media/{$file->uuid}")
            ->assertJsonPath('usages.0.label', 'Oksana')
            ->assertJsonPath('usages.0.url', "/admin/users/{$user->id}/edit");
    }

    public function test_rows_and_details_carry_the_short_type_while_mime_stays_full(): void
    {
        $docx = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        $file = $this->file(['mime' => $docx, 'original_name' => 'letter.docx']);

        $this->admin()->get('/admin/media')
            ->assertInertia(fn (Assert $page) => $page->where('table.rows.0.type', 'docx')->where('table.rows.0.mime', $docx));
        $this->admin()->getJson("/admin/media/{$file->uuid}")->assertJsonPath('type', 'docx')->assertJsonPath('mime', $docx);
    }

    private function filterUrl(string $type, string $extra = ''): string
    {
        return '/admin/media?filters[0][c]=mime&filters[0][o]=eq&filters[0][v]='.urlencode($type).$extra;
    }

    /** @return list<string> */
    private function names(string $url): array
    {
        return collect($this->admin()->get($url)->viewData('page')['props']['table']['rows'])->pluck('name')->all();
    }

    public function test_the_type_filter_shows_only_files_of_that_type_and_offers_the_labels(): void
    {
        $this->file(['original_name' => 'a.pdf', 'mime' => 'application/pdf']);
        $this->file(['original_name' => 'b.jpg', 'mime' => 'image/jpeg']);

        $this->admin()->get($this->filterUrl('pdf'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.meta.total', 1)
                ->where('table.rows.0.name', 'a.pdf')
                ->where('typeOptions', ['jpg', 'png', 'webp', 'gif', 'pdf', 'doc', 'docx', 'xlsx']));
    }

    public function test_the_type_filter_works_with_search_and_paging(): void
    {
        foreach (range(1, 20) as $i) {
            $this->file(['original_name' => "report{$i}.pdf", 'mime' => 'application/pdf', 'created_at' => now()->subMinutes(100 - $i)]);
        }
        $this->file(['original_name' => 'report.jpg', 'mime' => 'image/jpeg']);
        $this->file(['original_name' => 'other.pdf', 'mime' => 'application/pdf']);

        $this->admin()->get($this->filterUrl('pdf', '&q=report&per_page=15&page=2'))
            ->assertInertia(fn (Assert $page) => $page->has('table.rows', 5)->where('table.meta.total', 20)->where('table.meta.page', 2));
        $this->assertSame(['report.jpg'], $this->names($this->filterUrl('jpg', '&q=report')));
    }

    public function test_an_unknown_type_in_the_filter_is_ignored(): void
    {
        $this->file(['original_name' => 'a.pdf', 'mime' => 'application/pdf']);
        $this->file(['original_name' => 'b.jpg', 'mime' => 'image/jpeg']);

        $this->admin()->get($this->filterUrl('nope'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('table.meta.total', 2)->where('table.query.filters', []));
    }

    public function test_the_type_filter_matches_every_mime_that_shares_the_label(): void
    {
        config(['files.profiles.image.mimes' => ['image/jpeg' => 'jpg', 'image/pjpeg' => 'jpg']]);
        $this->file(['original_name' => 'a.jpg', 'mime' => 'image/jpeg']);
        $this->file(['original_name' => 'b.jpg', 'mime' => 'image/pjpeg']);
        $this->file(['original_name' => 'c.pdf', 'mime' => 'application/pdf']);

        $this->assertEqualsCanonicalizing(['a.jpg', 'b.jpg'], $this->names($this->filterUrl('jpg')));
    }

    public function test_sorting_by_type_still_works(): void
    {
        $this->file(['original_name' => 'a.pdf', 'mime' => 'application/pdf']);
        $this->file(['original_name' => 'b.jpg', 'mime' => 'image/jpeg']);

        $this->assertSame(['a.pdf', 'b.jpg'], $this->names('/admin/media?sort=mime&dir=asc'));
    }

    public function test_a_row_has_the_preview_of_images_only(): void
    {
        $image = $this->file(['mime' => 'image/png', 'profile' => 'image', 'path' => ($u = File::newUuid()).'.png', 'uuid' => $u, 'original_name' => 'pic.png']);
        $this->file(['original_name' => 'doc.pdf']);

        $this->admin()->get('/admin/media?sort=name&dir=asc')
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.rows.0.name', 'doc.pdf')->where('table.rows.0.preview', null)
                ->where('table.rows.1.url', Storage::disk('public')->url($image->path))
                ->where('table.rows.1.preview', Storage::disk('public')->url('styles/thumb/'.$image->uuid.'.webp')));
    }

    public function test_the_details_show_the_original_url_not_the_style(): void
    {
        $image = $this->file(['mime' => 'image/png', 'profile' => 'image', 'path' => ($u = File::newUuid()).'.png', 'uuid' => $u, 'original_name' => 'pic.png']);

        $this->admin()->getJson('/admin/media/'.$image->uuid)
            ->assertOk()
            ->assertJsonPath('url', Storage::disk('public')->url($image->path));
    }

    public function test_search_matches_the_name_and_treats_wildcards_literally(): void
    {
        $this->file(['original_name' => 'annual report.pdf']);
        $this->file(['original_name' => 'photo.pdf']);
        $this->file(['original_name' => '100%.pdf']);

        $names = fn (string $q) => collect($this->admin()->get('/admin/media?q='.urlencode($q))->viewData('page')['props']['table']['rows'])->pluck('name')->all();

        $this->assertSame(['annual report.pdf'], $names('report'));
        $this->assertSame(['100%.pdf'], $names('%'));
        $this->assertSame([], $names('_'));
    }

    public function test_sorting_by_uses_and_size(): void
    {
        $small = $this->file(['size' => 10, 'original_name' => 'small.pdf']);
        $big = $this->file(['size' => 99, 'original_name' => 'big.pdf']);
        $this->use($small);

        $order = fn (string $query) => collect($this->admin()->get("/admin/media?{$query}")->viewData('page')['props']['table']['rows'])->pluck('name')->all();

        $this->assertSame(['small.pdf', 'big.pdf'], $order('sort=uses&dir=desc'));
        $this->assertSame(['small.pdf', 'big.pdf'], $order('sort=size&dir=asc'));
        $this->assertSame(['big.pdf', 'small.pdf'], $order('sort=size&dir=desc'));
        $this->assertNotNull($big);
    }

    public function test_the_default_order_is_newest_first_and_the_list_is_paged(): void
    {
        foreach (range(1, 20) as $i) {
            $this->file(['original_name' => "f{$i}.pdf", 'created_at' => now()->subMinutes(100 - $i)]);
        }

        $this->admin()->get('/admin/media?per_page=15')
            ->assertInertia(fn (Assert $page) => $page
                ->has('table.rows', 15)
                ->where('table.rows.0.name', 'f20.pdf')
                ->where('table.meta.total', 20));
    }

    public function test_an_empty_library_has_no_rows(): void
    {
        $this->admin()->get('/admin/media')->assertInertia(fn (Assert $page) => $page->has('table.rows', 0)->where('table.meta.total', 0));
    }

    public function test_the_query_count_does_not_depend_on_the_number_of_files(): void
    {
        // Warm-up: the first request of a process also reads the cached app settings; the counted ones must not.
        $this->admin()->get('/admin/media')->assertOk();

        $count = function (int $files) {
            DB::table('media_relations')->delete();
            File::query()->delete();
            foreach (range(1, $files) as $i) {
                $this->use($this->file(['user_id' => User::factory()->create()->id]), $i);
            }

            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->admin()->get('/admin/media')->assertOk();
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };

        $this->assertSame($count(2), $count(8));
    }

    // --- show ---

    public function test_show_lists_where_the_file_is_used_with_a_link_to_the_owner(): void
    {
        ContentType::create(['key' => 'article', 'name' => 'Article']);
        $node = app(ContentNodeCreator::class)->create(ContentType::findByKey('article'), ['title' => 'Привіт'], 'hello', authorId: $this->authorId());
        $file = $this->file(['original_name' => 'cover.pdf']);
        $this->use($file, $node->id, 'cover');
        $this->use($file, 4040, 'gallery', 'mystery', 'uk');

        $this->admin()->getJson("/admin/media/{$file->uuid}")
            ->assertOk()
            ->assertJsonPath('name', 'cover.pdf')
            ->assertJsonPath('url', Storage::disk('public')->url($file->path))
            ->assertJsonPath('usages.0', [
                'owner_type' => 'content_node', 'owner_id' => $node->id, 'label' => 'Привіт',
                'url' => "/admin/content-types/article/nodes/{$node->uuid}/edit", 'role' => 'cover', 'lang' => 'default',
            ])
            ->assertJsonPath('usages.1', [
                'restricted' => true, 'owner_type' => '', 'owner_id' => null, 'label' => 'Used elsewhere', 'url' => null, 'role' => '', 'lang' => '',
            ]);
    }

    public function test_show_lists_the_use_in_a_value_of_a_type_with_a_link_to_its_settings(): void
    {
        $news = ContentType::create(['key' => 'news', 'name' => 'News']);
        $landing = ContentType::create(['key' => 'landing', 'name' => 'Landing', 'kind' => 'page']);
        $file = $this->file();
        $this->use($file, $news->id, 'section_cover', 'content_type');
        $this->use($file, $landing->id, 'meta_image', 'content_type');

        $this->admin()->getJson("/admin/media/{$file->uuid}")
            ->assertOk()
            ->assertJsonPath('usages.0', [
                'owner_type' => 'content_type', 'owner_id' => $news->id, 'label' => 'News',
                'url' => '/admin/content-types/news/edit', 'role' => 'section_cover', 'lang' => 'default',
            ])
            ->assertJsonPath('usages.1.url', '/admin/page-types/landing/edit');
    }

    public function test_describing_types_does_not_depend_on_their_number(): void
    {
        $file = $this->file();
        foreach (range(1, 6) as $i) {
            $type = ContentType::create(['key' => "t{$i}", 'name' => "T{$i}"]);
            $this->use($file, $type->id, 'section_cover', 'content_type');
        }
        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->admin()->getJson("/admin/media/{$this->lastUuid}")->assertOk();
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };
        $this->lastUuid = $file->uuid;
        $six = $count();
        $other = $this->file();
        $this->use($other, ContentType::findByKey('t1')->id, 'section_cover', 'content_type');
        $this->lastUuid = $other->uuid;

        $this->assertSame($six, $count());
    }

    public function test_show_carries_the_description_and_the_row_does_not(): void
    {
        $file = $this->file(['description' => 'Front cover']);

        $this->admin()->getJson("/admin/media/{$file->uuid}")->assertJsonPath('description', 'Front cover');
        $this->admin()->getJson("/admin/media/{$this->file()->uuid}")->assertJsonPath('description', null);
        $this->admin()->get('/admin/media')->assertInertia(fn ($page) => $page->missing('table.rows.0.description'));
    }

    public function test_show_lists_the_translations_with_language_names(): void
    {
        $file = $this->file(['description' => 'Лицьова обкладинка']);
        $this->assertSame([], $this->admin()->getJson("/admin/media/{$file->uuid}")->json('translations'));

        AppLanguage::factory()->active()->create(['code' => 'en', 'name' => 'English', 'weight' => 0]);
        AppLanguage::factory()->active()->create(['code' => 'pl', 'name' => 'Polski', 'weight' => 1]);
        app(ContentLanguages::class)->setBaseLang('xx');
        app(ContentLanguages::class)->setMultilingual(true);
        app(FileDescriptions::class)->save($file->uuid, 'pl', 'Okładka', 'Okładka nazwa');
        app(FileDescriptions::class)->save($file->uuid, 'en', 'Front cover');

        $this->admin()->getJson("/admin/media/{$file->uuid}")
            ->assertJsonPath('description', 'Лицьова обкладинка')
            ->assertJsonPath('translations', [
                ['lang' => 'en', 'language' => 'English', 'name' => null, 'description' => 'Front cover'],
                ['lang' => 'pl', 'language' => 'Polski', 'name' => 'Okładka nazwa', 'description' => 'Okładka'],
            ]);
    }

    public function test_show_of_an_unused_file_has_no_usages_and_an_unknown_uuid_is_404(): void
    {
        $file = $this->file();

        $this->admin()->getJson("/admin/media/{$file->uuid}")->assertOk()->assertJsonPath('usages', []);
        $this->admin()->getJson('/admin/media/'.str_repeat('0', 32))->assertNotFound();
    }

    // --- destroy ---

    public function test_an_unused_file_is_deleted_with_its_disk_file(): void
    {
        $file = $this->file();

        $this->admin()->delete("/admin/media/{$file->uuid}")->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseMissing('media', ['uuid' => $file->uuid]);
        Storage::disk('public')->assertMissing($file->path);
    }

    public function test_a_used_file_is_refused_even_for_a_direct_request(): void
    {
        $file = $this->file();
        $this->use($file);

        $this->admin()->delete("/admin/media/{$file->uuid}")->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseHas('media', ['uuid' => $file->uuid]);
        Storage::disk('public')->assertExists($file->path);
    }

    public function test_a_file_missing_on_disk_is_deleted_without_an_error(): void
    {
        $file = $this->file(onDisk: false);

        $this->admin()->delete("/admin/media/{$file->uuid}")->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseMissing('media', ['uuid' => $file->uuid]);
    }

    public function test_a_file_released_by_its_owner_can_then_be_deleted(): void
    {
        $file = $this->file();
        (new FileRelations)->sync('content_node', 1, 'cover', 'default', [$file->uuid]);
        $this->admin()->delete("/admin/media/{$file->uuid}")->assertSessionHas('error');

        (new FileRelations)->sync('content_node', 1, 'cover', 'default', []);
        $this->admin()->delete("/admin/media/{$file->uuid}")->assertSessionHas('success');

        $this->assertDatabaseMissing('media', ['uuid' => $file->uuid]);
    }
}
