<?php

namespace Webtack\Ecms\Tests\Feature\Content;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\Term;
use Webtack\Ecms\Models\Vocabulary;
use Webtack\Ecms\Tests\Feature\Content\Concerns\BuildsArticleType;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

/**
 * Translated field labels kept in `content_fields.settings.labels`.
 */
class ContentFieldLabelsTest extends TestCase
{
    use BuildsArticleType, RefreshDatabase;

    private function field(string $key = 'note'): ContentField
    {
        $this->articleType();

        return ContentField::query()->where('content_type', 'article')->where('key', $key)->firstOrFail();
    }

    public function test_labels_are_stored_under_normalised_codes_and_read_without_fallback(): void
    {
        $field = $this->field();

        $field->setLabel('en', 'Opening hours');
        $field->setLabel('pt-br', 'Horário');

        $this->assertSame(['labels' => ['en' => 'Opening hours', 'pt-BR' => 'Horário']], $field->fresh()->settings);
        $this->assertSame('Horário', $field->fresh()->label('pt-BR'));
        $this->assertNull($field->fresh()->label('pl'));
    }

    public function test_removing_the_last_label_leaves_no_empty_object(): void
    {
        $field = $this->field();
        $field->setLabel('en', 'Hours');

        $field->setLabel('en', '');

        $this->assertNull($field->fresh()->settings);
    }

    public function test_a_type_without_settings_can_hold_labels_and_nothing_else(): void
    {
        $field = $this->field('note');
        $field->setLabel('en', 'Note');

        $this->assertSame(['labels' => ['en' => 'Note']], $field->fresh()->settings);
    }

    public function test_other_settings_are_kept(): void
    {
        $this->articleType();
        $image = ContentField::create(['content_type' => 'article', 'key' => 'cover', 'name' => 'Cover', 'type' => 'image', 'settings' => ['max_size' => 100]]);

        $image->setLabel('en', 'Cover');
        $image->setLabel('en', null);

        $this->assertSame(['max_size' => 100], $image->fresh()->settings);
    }

    public function test_a_too_long_label_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->field()->setLabel('en', str_repeat('a', 256));
    }

    public function test_a_term_field_with_values_accepts_a_labels_only_save(): void
    {
        $this->articleType();
        $vocabulary = Vocabulary::factory()->create(['key' => 'tags', 'name' => 'Tags']);
        $field = ContentField::create(['content_type' => 'article', 'key' => 'category', 'name' => 'Category', 'type' => 'term', 'settings' => ['vocabulary' => 'tags']]);
        $term = Term::factory()->create(['vocabulary_key' => $vocabulary->key]);
        $node = $this->makeArticle(['title' => 'A', 'category' => $term->uuid]);

        $field->setLabel('en', 'Kind');

        $this->assertSame(['vocabulary' => 'tags', 'labels' => ['en' => 'Kind']], $field->fresh()->settings);
        $this->assertNotNull($node);
    }

    public function test_the_base_form_keeps_the_labels_for_every_kind_of_settings(): void
    {
        $this->articleType();
        $admin = User::factory()->create();
        Vocabulary::factory()->create(['key' => 'tags', 'name' => 'Tags']);
        ContentType::create(['key' => 'other', 'name' => 'Other']);
        $cases = [
            ['string', null, []],
            ['image', ['max_size' => 100], ['settings' => ['max_size' => 200]]],
            ['term', ['vocabulary' => 'tags'], ['settings' => ['vocabulary' => 'tags']]],
            ['node_ref', ['content_type' => 'other'], ['settings' => ['content_type' => 'other']]],
            ['type_ref', ['kind' => 'content'], ['settings' => ['kind' => 'page']]],
        ];

        foreach ($cases as $i => [$type, $settings, $extra]) {
            $field = ContentField::create(['content_type' => 'article', 'key' => "f{$i}", 'name' => 'F', 'type' => $type, 'settings' => $settings]);
            $field->setLabel('en', 'Label');

            $this->actingAs($admin)->put("/admin/content-types/article/fields/f{$i}", [
                'name' => 'Renamed', 'type' => $type, 'weight' => 1, ...$extra,
                // A client echoing labels must not be able to change them through the base form.
                ...(isset($extra['settings']) ? ['settings' => [...$extra['settings'], 'labels' => ['en' => 'Hacked']]] : []),
            ])->assertSessionHasNoErrors();

            $this->assertSame('Label', $field->fresh()->label('en'), $type);
            $this->assertSame('Renamed', $field->fresh()->name);
        }
    }
}
