<?php

namespace Webtack\Ecms\Tests\Feature\Content;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Content\ContentValueReader;
use Webtack\Ecms\Content\TranslationSaver;
use Webtack\Ecms\Content\ValueOwner;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Tests\Feature\Content\Concerns\BuildsArticleType;
use Webtack\Ecms\Tests\TestCase;

class TranslationSaverTest extends TestCase
{
    use BuildsArticleType, RefreshDatabase;

    private function saver(): TranslationSaver
    {
        return app(TranslationSaver::class);
    }

    private function read($node, array $keys, string $lang): array
    {
        return app(ContentValueReader::class)->load([$node], $keys, $lang)[$node->id];
    }

    private function assertInvalid(callable $call, string $key): void
    {
        try {
            $call();
            $this->fail('A ValidationException was expected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($key, $e->errors());
        }
    }

    public function test_only_values_that_differ_from_the_base_are_stored(): void
    {
        $this->enableLanguages('en');
        $node = $this->makeArticle(['title' => 'Привіт', 'body' => 'Основа']);

        $this->saver()->save($node, 'en', ['title' => 'Привіт', 'body' => 'Body']);

        $this->assertSame(['title' => null, 'body' => 'Body'], $this->read($node, ['title', 'body'], 'en'));
        $this->assertSame('Основа', $this->read($node, ['body'], 'default')['body']);
    }

    public function test_a_translation_that_now_equals_the_base_is_removed(): void
    {
        $this->enableLanguages('en');
        $node = $this->makeArticle(['title' => 'Привіт']);
        $this->saver()->save($node, 'en', ['title' => 'Hello']);

        $this->saver()->save($node, 'en', ['title' => 'Привіт']);

        $this->assertNull($this->read($node, ['title'], 'en')['title']);
    }

    public function test_an_emptied_field_loses_its_translation_and_required_is_not_enforced(): void
    {
        $this->enableLanguages('en');
        $node = $this->makeArticle(['title' => 'Привіт']);
        $this->saver()->save($node, 'en', ['title' => 'Hello']);

        $this->saver()->save($node, 'en', ['title' => '']);

        $this->assertNull($this->read($node, ['title'], 'en')['title']);
    }

    public function test_other_fields_and_languages_are_untouched(): void
    {
        $this->enableLanguages('en', 'pl');
        $node = $this->makeArticle(['title' => 'Привіт', 'body' => 'Основа']);
        $this->saver()->save($node, 'pl', ['title' => 'Czesc']);
        $this->saver()->save($node, 'en', ['title' => 'Hello', 'body' => 'Body']);

        $this->saver()->save($node, 'en', ['title' => 'Hi']);

        $this->assertSame('Hi', $this->read($node, ['title'], 'en')['title']);
        $this->assertSame('Body', $this->read($node, ['body'], 'en')['body']);
        $this->assertSame('Czesc', $this->read($node, ['title'], 'pl')['title']);
    }

    public function test_a_non_translatable_field_is_rejected_and_nothing_is_stored(): void
    {
        $this->enableLanguages('en');
        $node = $this->makeArticle(['title' => 'Привіт']);

        $this->assertInvalid(fn () => $this->saver()->save($node, 'en', ['title' => 'Hello', 'visits' => 3]), 'visits');

        $this->assertNull($this->read($node, ['title'], 'en')['title']);
    }

    public function test_an_invalid_language_is_rejected(): void
    {
        $node = $this->makeArticle(['title' => 'Привіт']);

        $this->assertInvalid(fn () => $this->saver()->save($node, 'en', ['title' => 'Hello']), 'lang');

        $this->enableLanguages('en');
        $this->assertInvalid(fn () => $this->saver()->save($node, 'de', ['title' => 'Hallo']), 'lang');
        $this->assertInvalid(fn () => $this->saver()->save($node, 'default', ['title' => 'Hallo']), 'lang');
    }

    public function test_one_invalid_value_stores_nothing(): void
    {
        $this->enableLanguages('en');
        $node = $this->makeArticle(['title' => 'Привіт']);

        $this->assertInvalid(fn () => $this->saver()->save($node, 'en', ['title' => 'Hello', 'body' => ['not', 'a', 'text']]), 'body');

        $this->assertSame(0, DB::table('content_values_string')->where('lang', 'en')->count());
    }

    public function test_a_multiple_field_is_compared_and_saved_as_a_whole_list(): void
    {
        $this->enableLanguages('en');
        $node = $this->makeArticle(['title' => 'Привіт', 'tags' => ['a', 'b']]);

        $this->saver()->save($node, 'en', ['tags' => ['a', 'b']]);
        $this->assertSame([], $this->read($node, ['tags'], 'en')['tags']);

        $this->saver()->save($node, 'en', ['tags' => ['b', 'a']]);
        $this->assertSame(['b', 'a'], $this->read($node, ['tags'], 'en')['tags']);

        $this->saver()->save($node, 'en', ['tags' => ['a', 'c']]);
        $this->assertSame(['a', 'c'], $this->read($node, ['tags'], 'en')['tags']);
        $this->assertSame(['a', 'b'], $this->read($node, ['tags'], 'default')['tags']);
    }

    public function test_composed_values_are_compared_by_their_stored_form(): void
    {
        $this->enableLanguages('en');
        $this->articleType();
        ContentField::create(['content_type' => 'article', 'key' => 'clip', 'name' => 'Clip', 'type' => 'youtube', 'is_translatable' => true]);
        $node = $this->makeArticle(['title' => 'Привіт', 'clip' => ['url' => 'https://youtu.be/dQw4w9WgXcQ', 'title' => 'Base']]);

        $this->saver()->save($node, 'en', ['clip' => ['url' => 'https://youtu.be/dQw4w9WgXcQ', 'title' => 'Base']]);
        $this->assertNull($this->read($node, ['clip'], 'en')['clip']);

        $this->saver()->save($node, 'en', ['clip' => ['url' => 'https://youtu.be/dQw4w9WgXcQ', 'title' => 'Other']]);
        $this->assertSame('Other', $this->read($node, ['clip'], 'en')['clip']['title']);
    }

    public function test_type_values_are_saved_and_cleared(): void
    {
        $this->enableLanguages('en');
        $type = ContentType::create(['key' => 'news', 'name' => 'News']);
        $owner = ValueOwner::forType($type);
        $read = fn () => app(ContentValueReader::class)->loadForType($type, ['section_title', 'meta_title'], 'en');

        $this->saver()->save($owner, 'en', ['section_title' => 'News', 'meta_title' => 'SEO']);
        $this->assertSame(['section_title' => 'News', 'meta_title' => 'SEO'], $read());

        $this->assertInvalid(fn () => $this->saver()->save($owner, 'en', ['title' => 'x']), 'title');

        $this->saver()->clear($owner, 'en');
        $this->assertSame(['section_title' => null, 'meta_title' => null], $read());
    }

    public function test_clear_removes_every_value_of_one_language_only(): void
    {
        $this->enableLanguages('en', 'pl');
        $node = $this->makeArticle(['title' => 'Привіт']);
        $this->saver()->save($node, 'en', ['title' => 'Hello']);
        $this->saver()->save($node, 'pl', ['title' => 'Czesc']);

        $this->saver()->clear($node, 'en');

        $this->assertNull($this->read($node, ['title'], 'en')['title']);
        $this->assertSame('Czesc', $this->read($node, ['title'], 'pl')['title']);
        $this->assertSame('Привіт', $this->read($node, ['title'], 'default')['title']);
    }
}
