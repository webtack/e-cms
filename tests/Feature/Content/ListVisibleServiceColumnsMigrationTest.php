<?php

namespace Webtack\Ecms\Tests\Feature\Content;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Tests\TestCase;

class ListVisibleServiceColumnsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'database/migrations/2026_11_02_190000_append_service_columns_to_list_visible.php';

    private function rerun(): void
    {
        Artisan::call('migrate:rollback', ['--path' => $this->packagePath(self::MIGRATION), '--realpath' => true]);
        Artisan::call('migrate', ['--path' => $this->packagePath(self::MIGRATION), '--realpath' => true]);
    }

    /** @return array<string, mixed>|null */
    private function settings(string $key): ?array
    {
        return ContentType::findByKey($key)->settings;
    }

    public function test_the_legacy_service_columns_are_appended_to_a_non_empty_visible(): void
    {
        ContentType::create(['key' => 'article', 'name' => 'Article', 'settings' => ['list' => ['visible' => ['title', 'meta_title'], 'search' => ['title']], 'other' => 1]]);

        $this->rerun();

        $this->assertSame(['title', 'meta_title', 'slug', 'status', 'published_at'], $this->settings('article')['list']['visible']);
        $this->assertSame(['title'], $this->settings('article')['list']['search']);
        $this->assertSame(1, $this->settings('article')['other']);
    }

    public function test_types_without_visible_are_not_changed(): void
    {
        ContentType::create(['key' => 'plain', 'name' => 'Plain']);
        ContentType::create(['key' => 'sorted', 'name' => 'Sorted', 'settings' => ['list' => ['sort' => ['field' => 'title', 'direction' => 'asc']]]]);
        ContentType::create(['key' => 'empty', 'name' => 'Empty', 'settings' => ['list' => ['visible' => []]]]);

        $this->rerun();

        $this->assertNull($this->settings('plain'));
        $this->assertArrayNotHasKey('visible', $this->settings('sorted')['list']);
        $this->assertSame([], $this->settings('empty')['list']['visible']);
    }

    public function test_existing_service_keys_are_kept_in_place_and_not_duplicated(): void
    {
        ContentType::create(['key' => 'article', 'name' => 'Article', 'settings' => ['list' => ['visible' => ['status', 'title', 'unpublished_at']]]]);

        $this->rerun();
        $this->assertSame(['status', 'title', 'unpublished_at', 'slug', 'published_at'], $this->settings('article')['list']['visible']);

        $this->rerun();
        $this->assertSame(['status', 'title', 'unpublished_at', 'slug', 'published_at'], $this->settings('article')['list']['visible']);
    }
}
