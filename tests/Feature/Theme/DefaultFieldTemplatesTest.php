<?php

namespace Webtack\Ecms\Tests\Feature\Theme;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Webtack\Ecms\Content\ContentNodeCreator;
use Webtack\Ecms\Content\ContentValueReader;
use Webtack\Ecms\Content\ContentValueWriter;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\File;
use Webtack\Ecms\Models\Vocabulary;
use Webtack\Ecms\Taxonomy\TermWriter;
use Webtack\Ecms\Tests\TestCase;
use Webtack\Ecms\Theme\FieldRenderer;
use Webtack\Ecms\Theme\ThemeManager;

/**
 * The field templates of the real `default` theme: every registered field type renders HTML for a typical value,
 * and what a visitor could abuse (markup in text, a script in rich text, a `javascript:` link) is not printed live.
 */
class DefaultFieldTemplatesTest extends TestCase
{
    use RefreshDatabase;

    private const ID = 'dQw4w9WgXcQ';

    private ContentType $type;

    private ContentNode $node;

    /** @var array<string, string> field type => a fragment its HTML must contain */
    private array $expected = [];

    protected function setUp(): void
    {
        parent::setUp();

        // These tests are about `default`, whatever APP_THEME of the environment says.
        config(['theme.active' => 'default']);
        $this->app->forgetInstance(ThemeManager::class);
        $this->app->forgetScopedInstances();
        $this->app->make(ThemeManager::class)->registerViews();

        Storage::fake('public');
        app()->setLocale('en');
        Vocabulary::factory()->create(['key' => 'colors', 'name' => 'Colors']);
        $red = app(TermWriter::class)->create(Vocabulary::findByKey('colors'), ['translations' => ['default' => ['name' => 'Red']]]);
        $news = ContentType::create(['key' => 'news', 'name' => 'News', 'slug' => 'news']);
        $target = app(ContentNodeCreator::class)->create($news, ['title' => 'Target story'], slug: 'target', authorId: $this->authorId());
        $this->type = ContentType::create(['key' => 'sample', 'name' => 'Sample']);

        $uuid = File::newUuid();
        File::factory()->create(['uuid' => $uuid, 'profile' => 'image', 'mime' => 'image/png', 'path' => "{$uuid}.png", 'original_name' => 'cat.png', 'description' => 'A cat', 'size' => 1000]);
        $doc = File::newUuid();
        File::factory()->create(['uuid' => $doc, 'profile' => 'document', 'mime' => 'application/pdf', 'path' => "{$doc}.pdf", 'original_name' => 'price.pdf', 'description' => 'Price list', 'size' => 1000]);

        // field type => [settings, value, a fragment of the HTML]
        $cases = [
            'string' => [null, 'Plain', 'Plain'],
            'text' => [null, "Line one\nLine <b>two</b>", '<br />'],
            'richtext' => [null, '<p>Rich</p>', '<p>Rich</p>'],
            'integer' => [null, 42, '42'],
            'decimal' => [null, '12.5', '12.5'],
            'boolean' => [null, true, 'Yes'],
            'date' => [null, '2026-01-02', '2026-01-02'],
            'datetime' => [null, '2026-01-02 03:04:00', '2026-01-02'],
            'image' => [null, "{$uuid}.png", 'alt="A cat"'],
            'file' => [null, "{$doc}.pdf", '>price.pdf</a>'],
            'youtube' => [null, ['url' => 'https://youtu.be/'.self::ID, 'title' => 'Clip'], 'youtube-nocookie.com/embed/'.self::ID],
            'term' => [['vocabulary' => 'colors'], $red->uuid, 'Red'],
            'url' => [null, ['url' => 'https://example.com/x', 'title' => 'Example'], 'rel="noopener noreferrer"'],
            'type_ref' => [['kind' => 'content'], 'news', '<a href="/news">News</a>'],
            'node_ref' => [['content_type' => 'news'], $target->uuid, 'href="/news/target"'],
        ];

        $values = ['title' => 'Sample'];

        foreach ($cases as $ftype => [$settings, $value, $fragment]) {
            ContentField::create(['content_type' => 'sample', 'key' => 'f_'.$ftype, 'name' => ucfirst($ftype), 'type' => $ftype, 'settings' => $settings]);
            $values['f_'.$ftype] = $value;
            $this->expected[$ftype] = $fragment;
        }

        $this->node = app(ContentNodeCreator::class)->create($this->type, $values, slug: 'sample', authorId: $this->authorId());
    }

    private function html(string $ftype): string
    {
        $values = app(ContentValueReader::class)->load([$this->node])[$this->node->id];
        $field = ContentField::query()->where('content_type', 'sample')->where('key', 'f_'.$ftype)->firstOrFail();

        return app(FieldRenderer::class)->render($field, $values['f_'.$ftype], $this->node, $this->type);
    }

    public function test_every_registered_field_type_has_a_case_here(): void
    {
        $this->assertEqualsCanonicalizing(array_keys(config('content.field_types')), array_keys($this->expected));
    }

    public function test_every_registered_field_type_renders_html_with_the_default_theme(): void
    {
        foreach ($this->expected as $ftype => $fragment) {
            $html = $this->html($ftype);

            $this->assertStringContainsString("field-{$ftype} field--f_{$ftype}", $html, "The wrapper of {$ftype}.");
            $this->assertStringContainsString($fragment, $html, "The content of {$ftype}.");
        }
    }

    public function test_every_registered_field_type_has_its_own_template_in_default(): void
    {
        foreach (array_keys(config('content.field_types')) as $ftype) {
            $this->assertFileExists($this->packagePath("themes/default/views/fields/field-{$ftype}.blade.php"));
        }

        $this->assertFileExists($this->packagePath('themes/default/views/fields/field.blade.php'));
    }

    public function test_every_field_prints_its_label(): void
    {
        foreach (array_keys($this->expected) as $ftype) {
            // The label keeps its hook class first; the theme's utility classes may follow it.
            $this->assertMatchesRegularExpression('#<div class="field__label[^"]*">'.preg_quote(ucfirst($ftype), '#').'</div>#', $this->html($ftype), "The label of {$ftype}.");
        }
    }

    public function test_a_field_with_hide_label_prints_its_value_without_the_label(): void
    {
        ContentField::query()->where('content_type', 'sample')->update(['hide_label' => true]);

        foreach ($this->expected as $ftype => $fragment) {
            $html = $this->html($ftype);

            $this->assertStringNotContainsString('field__label', $html, "The label of {$ftype} is hidden.");
            $this->assertStringContainsString("field-{$ftype} field--f_{$ftype}", $html, "The wrapper of {$ftype}.");
            $this->assertStringContainsString($fragment, $html, "The content of {$ftype}.");
        }
    }

    public function test_richtext_is_styled_by_its_class_and_youtube_keeps_a_16_by_9_frame(): void
    {
        // The rich text styles of app.css apply to the item that carries the `richtext` class.
        $this->assertMatchesRegularExpression('#<div class="field__item[^"]*\brichtext\b[^"]*">#', $this->html('richtext'));
        $this->assertStringContainsString('aspect-video', $this->html('youtube'));
    }

    public function test_a_file_prints_its_name_and_description_and_an_image_its_description(): void
    {
        $file = $this->html('file');
        $image = $this->html('image');

        $this->assertStringContainsString('>price.pdf</a>', $file);
        $this->assertMatchesRegularExpression('#<p class="field__description[^"]*">Price list</p>#', $file);
        $this->assertStringContainsString('alt="A cat"', $image);
        $this->assertMatchesRegularExpression('#<p class="field__caption[^"]*">A cat</p>#', $image);
    }

    public function test_text_is_escaped(): void
    {
        $html = $this->html('text');

        $this->assertStringContainsString('Line &lt;b&gt;two&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<b>', $html);
    }

    public function test_rich_text_written_around_the_api_is_cleaned_on_output(): void
    {
        DB::table('content_values_text')
            ->where('content_node_id', $this->node->id)->where('field_key', 'f_richtext')
            ->update(['value' => '<p onclick="x()">Hi</p><script>alert(1)</script>']);

        $html = $this->html('richtext');

        $this->assertStringContainsString('<p>Hi</p>', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function test_a_table_in_rich_text_keeps_its_header_cells_and_cell_alignment(): void
    {
        DB::table('content_values_text')
            ->where('content_node_id', $this->node->id)->where('field_key', 'f_richtext')
            ->update(['value' => '<table><thead><tr><th>Time</th><th>Event</th></tr></thead><tbody><tr><td>10:00</td><td><p style="text-align: center">Opening</p></td></tr></tbody></table>']);

        $html = $this->html('richtext');

        $this->assertMatchesRegularExpression('#<div class="field__item[^"]*\brichtext\b[^"]*">\s*<table>#', $html);
        $this->assertStringContainsString('<th>Time</th>', $html);
        $this->assertStringContainsString('<td>10:00</td>', $html);
        $this->assertStringContainsString('<p style="text-align: center">Opening</p>', $html);
    }

    public function test_a_link_with_a_script_scheme_is_not_printed(): void
    {
        DB::table('content_values_string')
            ->where('content_node_id', $this->node->id)->where('field_key', 'f_url')
            ->update(['value' => 'javascript:alert(1)']);

        $this->assertSame('', $this->html('url'));
    }

    public function test_a_string_with_markup_is_escaped(): void
    {
        app(ContentValueWriter::class)->write($this->node, ['f_string' => '<script>alert(1)</script>']);

        $html = $this->html('string');

        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    /**
     * A `news` node `$from` with a multiple `node_ref` field `see` to nodes of its own type, rendered with the default theme.
     *
     * @param  list<ContentNode>  $targets
     */
    private function nodeRefHtml(ContentNode $from, bool $hideLabel = false): string
    {
        $field = ContentField::query()->where('content_type', 'news')->where('key', 'see')->firstOrFail();
        $field->update(['hide_label' => $hideLabel]);
        $values = app(ContentValueReader::class)->load([$from])[$from->id];

        return app(FieldRenderer::class)->render($field->refresh(), $values['see'], $from, ContentType::findByKey('news'), $values);
    }

    private function newsWithRefs(): array
    {
        $news = ContentType::findByKey('news');
        ContentField::create(['content_type' => 'news', 'key' => 'body', 'name' => 'Body', 'type' => 'string']);
        $news->update(['settings' => ['public' => ['list_fields' => ['body']]]]);
        ContentField::create(['content_type' => 'news', 'key' => 'see', 'name' => 'See also', 'type' => 'node_ref', 'is_multiple' => true, 'settings' => ['content_type' => 'news']]);
        $creator = app(ContentNodeCreator::class);
        $one = $creator->create($news, ['title' => 'One', 'body' => 'Body one'], slug: 'one', authorId: $this->authorId());
        $two = $creator->create($news, ['title' => 'Two', 'body' => 'Body two'], slug: 'two', authorId: $this->authorId());
        $from = $creator->create($news, ['title' => 'From', 'body' => 'Body from'], slug: 'from', authorId: $this->authorId());

        return [$from, $one, $two];
    }

    public function test_a_node_ref_prints_the_card_of_the_target_with_the_title_once(): void
    {
        [$from, $one] = $this->newsWithRefs();
        app(ContentValueWriter::class)->write($from, ['see' => [$one->uuid]]);

        $html = $this->nodeRefHtml($from);

        $this->assertStringContainsString('<div class="field__label', $html);
        $this->assertSame(1, substr_count($html, '<a class="underline-offset-4 hover:underline" href="/news/one">One</a>'));
        $this->assertSame(1, substr_count($html, 'One</a>'));
        $this->assertStringContainsString('Body one', $html);
    }

    public function test_a_multiple_node_ref_embeds_every_target_in_order(): void
    {
        [$from, $one, $two] = $this->newsWithRefs();
        app(ContentValueWriter::class)->write($from, ['see' => [$two->uuid, $one->uuid]]);

        $html = $this->nodeRefHtml($from);

        $this->assertLessThan(strpos($html, 'Body one'), strpos($html, 'Body two'));
    }

    public function test_a_node_ref_that_would_repeat_a_rendered_node_falls_back_to_a_link(): void
    {
        [$from] = $this->newsWithRefs();
        app(ContentValueWriter::class)->write($from, ['see' => [$from->uuid]]);

        $html = $this->nodeRefHtml($from);

        $this->assertStringContainsString('<a href="/news/from">From</a>', $html);
        $this->assertStringNotContainsString('Body from', $html);
        $this->assertStringNotContainsString('<article', $html);
    }

    public function test_a_node_ref_with_hide_label_embeds_without_the_label(): void
    {
        [$from, $one] = $this->newsWithRefs();
        app(ContentValueWriter::class)->write($from, ['see' => [$one->uuid]]);

        $html = $this->nodeRefHtml($from, hideLabel: true);

        // The card keeps the labels of its own fields; only the label of the reference is hidden.
        $this->assertStringNotContainsString('See also', $html);
        $this->assertStringContainsString('Body one', $html);
    }

    public function test_a_node_ref_to_an_invisible_node_prints_nothing(): void
    {
        [$from, $one] = $this->newsWithRefs();
        app(ContentValueWriter::class)->write($from, ['see' => [$one->uuid]]);
        $one->update(['is_active' => false]);

        $this->assertSame('', $this->nodeRefHtml($from));
    }
}
