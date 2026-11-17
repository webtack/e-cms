<?php

namespace Webtack\Ecms\Tests\Feature\Content;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Models\AppLanguage;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\MenuItemTranslation;
use Webtack\Ecms\Models\TermTranslation;
use Webtack\Ecms\Tests\TestCase;

class AppLanguageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_language_is_inactive_by_default(): void
    {
        $language = AppLanguage::create(['code' => 'en', 'name' => 'English']);

        $this->assertFalse($language->fresh()->is_active);
    }

    public function test_codes_are_normalised_and_duplicates_in_another_case_rejected(): void
    {
        $this->assertSame('pt-BR', AppLanguage::create(['code' => 'PT-br', 'name' => 'Português'])->fresh()->code);
        $this->assertSame('zh-Hant-TW', AppLanguage::normalize('ZH-hant-tw'));

        try {
            AppLanguage::create(['code' => 'pt-br', 'name' => 'Again']);
            $this->fail('Duplicate must be rejected.');
        } catch (ValidationException|QueryException $e) {
            $this->assertSame(1, AppLanguage::count());
        }
    }

    public function test_reserved_and_malformed_codes_are_rejected(): void
    {
        foreach (['default', 'DEFAULT', '!!', 'e', str_repeat('a', 17), 'en_US', ''] as $code) {
            try {
                AppLanguage::create(['code' => $code, 'name' => 'X']);
                $this->fail("Code [{$code}] must be rejected.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('code', $e->errors());
            }
        }
    }

    public function test_deactivation_keeps_the_values(): void
    {
        $language = AppLanguage::factory()->active()->create(['code' => 'en']);
        $this->storeValueIn('en');

        $language->update(['is_active' => false]);

        $this->assertFalse($language->fresh()->is_active);
        $this->assertSame(1, DB::table('content_values_string')->where('lang', 'en')->count());
    }

    public function test_a_language_with_values_cannot_be_deleted(): void
    {
        $language = AppLanguage::factory()->create(['code' => 'en']);
        $this->storeValueIn('en');

        try {
            $language->delete();
            $this->fail('Deletion must be rejected.');
        } catch (ValidationException) {
        }
        $this->assertTrue(AppLanguage::where('code', 'en')->exists());
    }

    public function test_a_language_without_values_can_be_deleted(): void
    {
        $language = AppLanguage::factory()->create(['code' => 'en']);
        $this->storeValueIn('de');

        $language->delete();

        $this->assertSame(0, AppLanguage::count());
    }

    public function test_a_language_with_a_term_or_menu_item_translation_cannot_be_deleted(): void
    {
        $withTerm = AppLanguage::factory()->create(['code' => 'en']);
        $withItem = AppLanguage::factory()->create(['code' => 'de']);
        TermTranslation::factory()->create(['lang' => 'en']);
        MenuItemTranslation::factory()->create(['lang' => 'de']);

        foreach ([$withTerm, $withItem] as $language) {
            try {
                $language->delete();
                $this->fail('Deletion must be rejected.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('code', $e->errors());
            }
        }

        $this->assertSame(2, AppLanguage::count());
    }

    private function storeValueIn(string $lang): void
    {
        $type = ContentType::factory()->create();
        $node = ContentNode::factory()->create(['content_type' => $type->key]);

        DB::table('content_values_string')->insert([
            'content_node_id' => $node->id,
            'content_node_uuid' => $node->uuid,
            'content_type' => $type->key,
            'field_key' => 'title',
            'lang' => $lang,
            'value' => 'x',
        ]);
    }
}
