<?php

namespace Webtack\Ecms\Tests\Feature\Theme;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\View\ViewException;
use Webtack\Ecms\Content\ContentValueReader;
use Webtack\Ecms\Content\ContentValueWriter;
use Webtack\Ecms\Content\PresentContext;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Tests\Feature\Content\Concerns\BuildsArticleType;
use Webtack\Ecms\Tests\Feature\Theme\Concerns\UsesFixtureThemes;
use Webtack\Ecms\Tests\TestCase;
use Webtack\Ecms\Theme\FieldRenderer;

class FieldRendererTest extends TestCase
{
    use BuildsArticleType, RefreshDatabase, UsesFixtureThemes;

    private ContentNode $node;

    /** @var array<string, mixed> */
    private array $values;

    protected function setUp(): void
    {
        parent::setUp();

        $this->node = $this->makeArticle(['title' => 'Hello', 'note' => 'Plain', 'tags' => ['a', 'b']]);
        $this->values = app(ContentValueReader::class)->load([$this->node])[$this->node->id];
    }

    private function field(string $key): ContentField
    {
        return ContentField::query()->where('content_type', 'article')->where('key', $key)->firstOrFail();
    }

    private function render(string $key): string
    {
        return app(FieldRenderer::class)->render($this->field($key), $this->values[$key], $this->node, ContentType::findByKey('article'));
    }

    public function test_the_candidates_go_from_the_key_of_the_field_to_the_global_template(): void
    {
        $this->assertSame([
            'article/field-string--note',
            'article/field-string',
            'article/field--note',
            'article/field',
            'fields/field-string--note',
            'fields/field-string',
            'fields/field--note',
            'fields/field',
        ], app(FieldRenderer::class)->candidates($this->field('note'), 'article'));
    }

    public function test_the_template_of_the_field_type_beats_the_template_of_the_key(): void
    {
        $this->makeTheme('default', ['fields/field' => 'GENERIC', 'fields/field--note' => 'KEY', 'fields/field-string' => 'TYPE']);
        $this->useTheme('default');

        $this->assertSame('TYPE', $this->render('note'));
    }

    public function test_the_key_of_the_field_is_the_most_specific_candidate(): void
    {
        $this->makeTheme('default', ['fields/field' => 'GENERIC', 'fields/field--note' => 'KEY', 'fields/field-string--note' => 'TYPE+KEY']);
        $this->useTheme('default');

        $this->assertSame('TYPE+KEY', $this->render('note'));
    }

    public function test_the_key_of_the_field_beats_the_generic_template_without_a_type_template(): void
    {
        $this->makeTheme('default', ['fields/field' => 'GENERIC', 'fields/field--note' => 'KEY']);
        $this->useTheme('default');

        $this->assertSame('KEY', $this->render('note'));
    }

    public function test_the_template_of_the_content_type_beats_the_global_one(): void
    {
        $this->makeTheme('default', ['fields/field-string' => 'GLOBAL', 'article/field-string' => 'ARTICLE']);
        $this->useTheme('default');

        $this->assertSame('ARTICLE', $this->render('note'));
    }

    public function test_a_field_that_is_not_multiple_still_gets_a_list_of_items(): void
    {
        $this->makeTheme('default', ['fields/field' => '{!! json_encode($items) !!}|{{ $multiple ? "multiple" : "single" }}']);
        $this->useTheme('default');

        $this->assertSame('["Plain"]|single', $this->render('note'));
        $this->assertSame('["a","b"]|multiple', $this->render('tags'));
    }

    public function test_a_field_without_a_value_renders_nothing(): void
    {
        $this->makeTheme('default', ['fields/field' => 'SHOWN']);
        $this->useTheme('default');
        $node = $this->makeArticle(['title' => 'Empty'], 'empty');
        $values = app(ContentValueReader::class)->load([$node])[$node->id];

        $renderer = app(FieldRenderer::class);
        $type = ContentType::findByKey('article');

        $this->assertSame('', $renderer->render($this->field('note'), $values['note'], $node, $type));
        $this->assertSame('', $renderer->render($this->field('tags'), $values['tags'], $node, $type));
    }

    public function test_the_hooks_run_from_the_general_name_to_the_type_and_can_change_the_candidates(): void
    {
        $this->makeTheme('default', [
            'fields/field' => '{{ implode(",", $order) }}',
            'fields/field-special' => 'SPECIAL',
        ], <<<'PHP'
<?php
use Webtack\Ecms\Theme\ThemeHooks;
return function (ThemeHooks $hooks): void {
    $hooks->preprocess('field', function (array &$vars) { $vars['order'] = ['field']; });
    $hooks->preprocess('field.string', function (array &$vars) { $vars['order'][] = 'field.string'; });
    $hooks->suggest('field.text', function (array &$candidates) { array_unshift($candidates, 'fields/field-special'); });
};
PHP);
        $this->useTheme('default');

        $this->assertSame('field,field.string', $this->render('note'));
        // The `field.text` suggestion does not touch a string field.
        $this->assertSame('field,field.string', $this->render('note'));
    }

    public function test_a_suggest_hook_of_the_field_type_picks_another_template(): void
    {
        $this->makeTheme('default', ['fields/field' => 'GENERIC', 'fields/field-special' => 'SPECIAL'], <<<'PHP'
<?php
use Webtack\Ecms\Theme\ThemeHooks;
return function (ThemeHooks $hooks): void {
    $hooks->suggest('field.string', function (array &$candidates) { array_unshift($candidates, 'fields/field-special'); });
};
PHP);
        $this->useTheme('default');

        $this->assertSame('SPECIAL', $this->render('note'));
    }

    public function test_the_directives_render_fields_from_the_template_variables(): void
    {
        $this->makeTheme('default', [
            'fields/field' => '[{{ $key }}:{{ implode("+", $items) }}]',
            'probe' => '@field($values, "note")@fields($values, ["tags"])|@fields($values)',
        ]);
        $manager = $this->useTheme('default');
        $type = ContentType::findByKey('article');
        $fields = ['note' => $this->field('note'), 'tags' => $this->field('tags')];

        $html = $manager->render(['probe'], ['node' => $this->node, 'type' => $type, 'values' => $this->values, 'fields' => $fields]);

        $this->assertSame('[note:Plain][tags:a+b]|[note:Plain][tags:a+b]', $html);
    }

    public function test_a_field_template_receives_the_values_of_the_node(): void
    {
        $this->makeTheme('default', [
            'fields/field--note' => '[{{ $values["title"] ?? "none" }}]',
            'probe' => '@field($values, "note")|@fields($values, ["note"])',
        ]);
        $manager = $this->useTheme('default');

        $html = $manager->render(['probe'], ['node' => $this->node, 'type' => ContentType::findByKey('article'), 'values' => $this->values]);

        $this->assertSame('[Hello]|[Hello]', $html);
    }

    public function test_a_field_rendered_without_node_values_gets_an_empty_array(): void
    {
        $this->makeTheme('default', ['fields/field--note' => '{{ json_encode($values) }}']);
        $this->useTheme('default');

        $this->assertSame('[]', $this->render('note'));
    }

    public function test_the_values_variable_adds_no_queries(): void
    {
        $this->makeTheme('default', [
            'fields/field--note' => '{{ $values["title"] ?? "-" }}',
            'probe' => '@field($values, "note")',
        ]);
        $manager = $this->useTheme('default');
        $vars = ['node' => $this->node, 'type' => ContentType::findByKey('article'), 'values' => $this->values];
        $manager->render(['probe'], $vars);

        DB::enableQueryLog();
        $manager->render(['probe'], $vars);
        $withValues = count(DB::getQueryLog());
        DB::flushQueryLog();
        $manager->render(['probe'], [...$vars, 'values' => ['note' => 'Plain']]);
        $withoutTitle = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($withoutTitle, $withValues);
    }

    public function test_a_field_the_type_does_not_have_renders_nothing(): void
    {
        $this->makeTheme('default', ['fields/field' => 'X', 'probe' => '@field($values, "gone")']);
        $manager = $this->useTheme('default');

        $html = $manager->render(['probe'], ['node' => $this->node, 'type' => ContentType::findByKey('article'), 'values' => $this->values]);

        $this->assertSame('', $html);
    }

    public function test_a_directive_without_the_node_explains_what_is_missing(): void
    {
        $this->makeTheme('default', ['probe' => '@field($values, "note")']);
        $manager = $this->useTheme('default');

        // Blade wraps what a template throws into a ViewException that keeps the original message.
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('The directive @field needs the template variables [$node, $type]');

        $manager->render(['probe'], ['values' => $this->values]);
    }

    public function test_the_documented_youtube_example_works(): void
    {
        // The example of docs/theme-templates.md: a theme hides the video captions of every youtube field.
        ContentField::create(['content_type' => 'article', 'key' => 'video', 'name' => 'Video', 'type' => 'youtube']);
        app(ContentValueWriter::class)->write($this->node, ['video' => ['url' => 'https://youtu.be/dQw4w9WgXcQ', 'title' => 'Clip']]);
        $this->values = app(ContentValueReader::class)->load([$this->node])[$this->node->id];

        $this->makeTheme('default', ['fields/field-youtube' => '{{ $items[0]["embed"] }}|{{ $items[0]["title"] ?? "no title" }}'], <<<'PHP'
<?php
use Webtack\Ecms\Theme\ThemeHooks;

return function (ThemeHooks $hooks): void {
    $hooks->preprocess('field.youtube', function (array &$vars) {
        $vars['items'] = array_map(fn (array $item) => [...$item, 'title' => null], $vars['items']);
    });
};
PHP);
        $this->useTheme('default');

        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ|no title', $this->render('video'));
    }

    public function test_the_template_receives_a_detached_field_named_with_the_label_in_the_reading_language(): void
    {
        $this->enableLanguages('en', 'pl');
        $field = $this->field('note');
        $field->setLabel('en', 'Opening hours');
        $original = $field->name;
        $this->makeTheme('default', ['fields/field' => '{{ $field->name }}']);
        $this->useTheme('default');
        $type = ContentType::findByKey('article');

        $render = function (string $lang) use ($type) {
            $this->app->instance(PresentContext::class, new PresentContext(app(ContentValueReader::class), $lang, fallback: true));
            $this->app->forgetInstance(FieldRenderer::class);

            return app(FieldRenderer::class)->render($this->field('note'), $this->values['note'], $this->node, $type);
        };

        $this->assertSame('Opening hours', $render('en'));
        $this->assertSame($original, $render('pl'));
        $this->assertSame($original, $this->field('note')->name);
    }
}
