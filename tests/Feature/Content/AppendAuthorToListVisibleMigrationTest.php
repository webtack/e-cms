<?php

namespace Webtack\Ecms\Tests\Feature\Content;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Tests\TestCase;

class AppendAuthorToListVisibleMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'database/migrations/2026_11_16_100000_append_author_to_list_visible.php';

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

    public function test_author_is_appended_to_a_custom_visible_list_once(): void
    {
        ContentType::create(['key' => 'article', 'name' => 'Article', 'settings' => ['list' => ['visible' => ['title', 'slug', 'status'], 'search' => ['title']], 'other' => 1]]);

        $this->rerun();
        $this->rerun();

        $this->assertSame(['title', 'slug', 'status', 'author'], $this->settings('article')['list']['visible']);
        $this->assertSame(['title'], $this->settings('article')['list']['search']);
        $this->assertSame(1, $this->settings('article')['other']);
    }

    public function test_default_composition_types_and_existing_author_are_not_changed(): void
    {
        ContentType::create(['key' => 'plain', 'name' => 'Plain']);
        ContentType::create(['key' => 'empty', 'name' => 'Empty', 'settings' => ['list' => ['visible' => []]]]);
        ContentType::create(['key' => 'only_title', 'name' => 'Only title', 'settings' => ['list' => ['visible' => ['title']]]]);
        ContentType::create(['key' => 'has_author', 'name' => 'Has author', 'settings' => ['list' => ['visible' => ['author', 'title']]]]);

        $this->rerun();

        $this->assertNull($this->settings('plain'));
        $this->assertSame([], $this->settings('empty')['list']['visible']);
        $this->assertSame(['title'], $this->settings('only_title')['list']['visible']);
        $this->assertSame(['author', 'title'], $this->settings('has_author')['list']['visible']);
    }
}
