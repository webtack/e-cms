<?php

namespace Webtack\Ecms\Content;

use Illuminate\Support\Collection;
use Webtack\Ecms\Files\FileDescriptions;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\File;
use Webtack\Ecms\Models\TermTranslation;

/**
 * Per-request cache that lets {@see FieldTypes\FieldType::present()} resolve references (terms, target
 * nodes, content types, file metadata) without a query per value.
 *
 * A list of N cards must cost a fixed number of queries, not N. So before rendering, the renderer calls
 * `preload*()` with every referenced id of the page (see {@see FieldTypes\PreloadsReferences}); each
 * `preload*()` runs ONE query for the ids it has not seen yet. A later `term()`, `node()` or `type()` is then
 * answered from memory. Asking for an id that was never preloaded still works: it is loaded alone and cached.
 *
 * Everything is read in the context language. Without `$fallback` nothing is substituted: a term without a
 * translation has no name. With `$fallback` (the public site in an additional language) each missing text is
 * replaced by the base-language one, field by field, and by no other language: term names, node titles,
 * file names and descriptions and field labels. The context is bound as a scoped service, so it lives for
 * one request.
 *
 * @example
 *   $context = app(PresentContext::class);
 *   $context->preloadTerms([$uuidA, $uuidB]);   // one query for both
 *   $context->term($uuidA);                     // "Red" | null
 *   $context->node($nodeUuid);                  // ['uuid' => '0190…', 'title' => 'Hello', 'url' => '/news/hello'] | null (not visible)
 *   $context->type('news');                     // ['key' => 'news', 'name' => 'News', 'url' => '/news'] | null
 * @example The public site reading English with the fallback
 *   $context = new PresentContext($reader, 'en', fallback: true);
 *   $context->term($uuidWithoutEnglishName);    // the base name
 *   $context->label($field);                    // "Opening hours" (English label) or the stored name
 */
class PresentContext
{
    /** @var array<string, string|null> term uuid => name */
    private array $terms = [];

    /** @var array<string, array{uuid: string, title: string|null, url: string|null}|null> node uuid => target */
    private array $nodes = [];

    /** @var array<string, array{key: string, name: string, url: string|null}|null> type key => type */
    private array $types = [];

    /** @var array<string, array{name: string, description: string|null, width: int|null, height: int|null}|null> file uuid => metadata in the context language */
    private array $files = [];

    /** @var array<string, string> type key => kind */
    private array $kinds = [];

    public function __construct(
        private readonly ContentValueReader $reader,
        /** The reading language of every value. */
        public string $lang = 'default',
        /** Replace a text missing in the reading language by the base-language one (the public site). */
        public bool $fallback = false,
    ) {}

    /**
     * Switch the reading language and drop everything resolved in the previous one. The public language
     * middleware calls it when a request is read in another language than the one this context was built for.
     *
     * @example $context->readIn('en', fallback: true);
     */
    public function readIn(string $lang, bool $fallback = false): void
    {
        $this->lang = $lang;
        $this->fallback = $fallback;
        $this->terms = $this->nodes = $this->files = $this->types = $this->kinds = [];
    }

    /**
     * The label of a field in the reading language: its translated label, else (with the fallback, or in the
     * base language) the stored name. Without the fallback a missing translation gives the stored name too, as a
     * label is never empty.
     *
     * @example $context->label($field); // "Schedule"
     */
    public function label(ContentField $field): string
    {
        if ($this->lang === config('content.default_lang')) {
            return $field->name;
        }

        return $field->label($this->lang) ?? $field->name;
    }

    /**
     * Load the names of the terms that are not cached yet, with one query.
     *
     * @param  list<string>  $uuids
     */
    public function preloadTerms(array $uuids): void
    {
        $missing = array_values(array_diff(array_unique($uuids), array_keys($this->terms)));

        if ($missing === []) {
            return;
        }

        $names = $this->termNames($missing, $this->lang);
        $base = $this->fallback && $this->lang !== config('content.default_lang')
            ? $this->termNames($missing, config('content.default_lang'))
            : collect();

        foreach ($missing as $uuid) {
            $this->terms[$uuid] = $names[$uuid] ?? $base[$uuid] ?? null;
        }
    }

    /**
     * The non-blank names of terms in one language.
     *
     * @param  list<string>  $uuids
     * @return Collection<string, string>
     */
    private function termNames(array $uuids, string $lang)
    {
        return TermTranslation::query()
            ->whereIn('term_uuid', $uuids)
            ->where('lang', $lang)
            ->whereNotNull('name')->where('name', '!=', '')
            ->pluck('name', 'term_uuid');
    }

    /** The name of a term in the context language (the base one with the fallback), or `null` when it has none. */
    public function term(string $uuid): ?string
    {
        $this->preloadTerms([$uuid]);

        return $this->terms[$uuid];
    }

    /**
     * Load the targets of the nodes that are not cached yet: one query for the visible nodes, one for their
     * types and one for their titles.
     *
     * @param  list<string>  $uuids
     */
    public function preloadNodes(array $uuids): void
    {
        $missing = array_values(array_diff(array_unique($uuids), array_keys($this->nodes)));

        if ($missing === []) {
            return;
        }

        $nodes = ContentNode::query()->visible()->whereIn('uuid', $missing)->get();
        $this->preloadTypes($nodes->pluck('content_type')->unique()->values()->all());
        $titles = $this->reader->load($nodes, ['title'], $this->lang, $this->fallback);

        foreach ($missing as $uuid) {
            $this->nodes[$uuid] = null;
        }

        foreach ($nodes as $node) {
            $this->nodes[$node->uuid] = [
                'uuid' => $node->uuid,
                'title' => is_string($titles[$node->id]['title'] ?? null) ? $titles[$node->id]['title'] : null,
                'url' => $this->nodeUrl($node),
            ];
        }
    }

    /**
     * The uuid, title and address of a VISIBLE node; `null` for a node that is missing, disabled, scheduled or expired.
     * The address is `null` for a block, which has none.
     *
     * @return array{uuid: string, title: string|null, url: string|null}|null
     */
    public function node(string $uuid): ?array
    {
        $this->preloadNodes([$uuid]);

        return $this->nodes[$uuid];
    }

    /**
     * Load the content types that are not cached yet, with one query.
     *
     * @param  list<string>  $keys
     */
    public function preloadTypes(array $keys): void
    {
        $missing = array_values(array_diff(array_unique($keys), array_keys($this->types)));

        if ($missing === []) {
            return;
        }

        foreach ($missing as $key) {
            $this->types[$key] = null;
        }

        foreach (ContentType::query()->whereIn('key', $missing)->get() as $type) {
            $this->kinds[$type->key] = $type->kind;
            $this->types[$type->key] = [
                'key' => $type->key,
                'name' => $type->name,
                // Only a `content` type has a public list; its slug is the first URL segment.
                'url' => $type->kind === 'content' && $type->slug !== null ? $this->localize('/'.$type->slug) : null,
            ];
        }
    }

    /**
     * The key, name and list address of a content type; `null` when the type does not exist.
     *
     * @return array{key: string, name: string, url: string|null}|null
     */
    public function type(string $key): ?array
    {
        $this->preloadTypes([$key]);

        return $this->types[$key];
    }

    /**
     * Load the name, description and pixel size of the files that are not cached yet, with one query (and one more
     * for the translations when the context language is an additional one).
     *
     * In an additional language the translated display name and description win; a file without a translated value
     * keeps the original name or the base description (never an empty one). The original name itself never changes.
     *
     * @param  list<string>  $uuids
     */
    public function preloadFiles(array $uuids): void
    {
        $missing = array_values(array_diff(array_unique($uuids), array_keys($this->files)));

        if ($missing === []) {
            return;
        }

        foreach ($missing as $uuid) {
            $this->files[$uuid] = null;
        }

        // Names and descriptions come with the fallback (translated, else the base value); the original name never changes.
        $rows = File::query()->whereIn('uuid', $missing)->get(['uuid', 'original_name', 'description', 'width', 'height']);
        $texts = app(FileDescriptions::class)->resolve($missing, $this->lang, $rows);

        foreach ($rows as $file) {
            $this->files[$file->uuid] = [
                'name' => $texts[$file->uuid]['name'],
                'description' => $texts[$file->uuid]['description'],
                'width' => $file->width,
                'height' => $file->height,
            ];
        }
    }

    /**
     * The client-side name, the description and the pixel size of a stored file; `null` when the file row is missing.
     * `width` and `height` are `null` for a file that is not an image. A template reads the size here instead of
     * opening the file, e.g. to choose an image style by the orientation.
     *
     * @return array{name: string, description: string|null, width: int|null, height: int|null}|null
     */
    public function file(string $uuid): ?array
    {
        $this->preloadFiles([$uuid]);

        return $this->files[$uuid];
    }

    /**
     * An internal address in the reading language: `/news/hello` is `/en/news/hello` on an English page; absolute,
     * service and already prefixed addresses are returned as they are ({@see LocalizedUrl::path()}).
     */
    public function localize(?string $url): ?string
    {
        return $url === null ? null : app(LocalizedUrl::class)->path($url);
    }

    /** The public address of a node: a page stands on its slug, a record under its type's list, a block has none. */
    private function nodeUrl(ContentNode $node): ?string
    {
        $type = $this->types[$node->content_type] ?? null;

        return match ($this->kinds[$node->content_type] ?? null) {
            'page' => $this->localize('/'.$node->slug),
            'content' => $type === null || $type['url'] === null ? null : $this->localize($type['url'].'/'.$node->slug),
            default => null,
        };
    }
}
