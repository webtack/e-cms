<?php

namespace Webtack\Ecms\Tests\Feature\Content\Concerns;

use Webtack\Ecms\Content\ContentLanguages;
use Webtack\Ecms\Content\ContentNodeCreator;
use Webtack\Ecms\Models\AppLanguage;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;

/**
 * Test helper: an `article` type with one field of each interesting shape.
 */
trait BuildsArticleType
{
    protected function articleType(): ContentType
    {
        $type = ContentType::create(['key' => 'article', 'name' => 'Article']);

        foreach ([
            ['visits', 'integer', []],
            ['price', 'decimal', []],
            ['tags', 'string', ['is_multiple' => true, 'is_translatable' => true]],
            ['published', 'boolean', []],
            ['released', 'date', []],
            ['updated', 'datetime', []],
            ['body', 'text', ['is_translatable' => true]],
            ['note', 'string', ['is_required' => false]],
        ] as [$key, $fieldType, $extra]) {
            ContentField::create(['content_type' => 'article', 'key' => $key, 'name' => ucfirst($key), 'type' => $fieldType, ...$extra]);
        }

        return $type;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    protected function makeArticle(array $values = ['title' => 'Hello'], ?string $slug = null): ContentNode
    {
        return app(ContentNodeCreator::class)->create(ContentType::findByKey('article') ?? $this->articleType(), $values, $slug, authorId: $this->authorId());
    }

    protected function enableLanguages(string ...$codes): void
    {
        foreach ($codes as $i => $code) {
            AppLanguage::factory()->active()->create(['code' => $code, 'weight' => $i]);
        }

        app(ContentLanguages::class)->setBaseLang('xx');
        app(ContentLanguages::class)->setMultilingual(true);
    }
}
