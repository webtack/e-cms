<?php

namespace Webtack\Ecms\Content;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;

/**
 * Builds slugs for new nodes.
 *
 * For `content` and `page` types the slug comes from the default-language title (Ukrainian-aware
 * transliteration), with a numeric suffix (`-2`, `-3`, …) when the slug is taken. For `block`
 * types it is `block-` plus 8 random lowercase letters. For `page` types a slug is also taken when it equals the
 * slug of a `content` type or a reserved path ({@see PublicPaths}), so `Admin` gives `admin-2`. Explicit slugs are
 * never touched here.
 *
 * @example
 *   $generator = app(SlugGenerator::class);
 *   $generator->forType($type, 'Про нас'); // "pro-nas", then "pro-nas-2" if taken
 *   $generator->forType($pageType, 'Admin'); // "admin-2": "admin" is a reserved path
 *   $generator->forType($blockType, null); // "block-qzmwkptr"
 */
class SlugGenerator
{
    /** Fallback slug when the title has no usable characters. */
    public const FALLBACK = 'node';

    private const RANDOM_LENGTH = 8;

    private const RANDOM_ATTEMPTS = 10;

    /** Room kept for a numeric suffix within the 190-character limit. */
    private const BASE_MAX_LENGTH = 180;

    /**
     * Generate a free slug appropriate for the kind of the given type.
     *
     * @throws RuntimeException When a free block identifier cannot be found.
     */
    public function forType(ContentType $type, ?string $title): string
    {
        return $type->kind === 'block' ? $this->forBlock() : $this->fromTitle($title, avoidPublicPaths: $type->kind === 'page');
    }

    /**
     * Slug from a title: transliterated, lowercase, hyphen separated, made unique with a suffix.
     *
     * @param  bool  $avoidPublicPaths  Also treat `content` type slugs and reserved paths as taken (for pages).
     *
     * @example
     *   $generator->fromTitle('News', avoidPublicPaths: true); // "news-2" when a content type has the slug "news"
     */
    public function fromTitle(?string $title, bool $avoidPublicPaths = false): string
    {
        $base = Str::limit(Str::slug((string) $title, '-', 'uk'), self::BASE_MAX_LENGTH, '');
        $base = trim($base, '-');

        if ($base === '') {
            $base = self::FALLBACK;
        }

        // One query for the base and all its numeric suffixes instead of probing one by one.
        $taken = DB::table('content_nodes')
            ->where('slug', $base)
            ->orWhere('slug', 'like', $base.'-%')
            ->pluck('slug')
            ->map(fn (string $slug) => strtolower($slug));

        if ($avoidPublicPaths) {
            $taken = $taken->merge($this->takenPublicPaths($base));
        }

        $taken = $taken->flip();

        if (! $taken->has($base)) {
            return $base;
        }

        for ($suffix = 2; $taken->has("{$base}-{$suffix}"); $suffix++);

        return "{$base}-{$suffix}";
    }

    /**
     * Slugs of `content` types and reserved paths equal to the base or one of its numeric suffixes.
     *
     * @return Collection<int, string>
     */
    private function takenPublicPaths(string $base): Collection
    {
        $types = DB::table('content_types')
            ->where('kind', 'content')
            ->where(fn ($query) => $query->where('slug', $base)->orWhere('slug', 'like', $base.'-%'))
            ->pluck('slug')
            ->map(fn (string $slug) => strtolower($slug));

        $paths = app(PublicPaths::class);
        $reserved = collect([...$paths->reserved(), ...$paths->languageCodes()])
            ->filter(fn (string $path) => $path === $base || str_starts_with($path, $base.'-'));

        return $types->merge($reserved)->values();
    }

    /**
     * `block-` plus 8 random lowercase letters, repeated until unused.
     *
     * @throws RuntimeException
     */
    public function forBlock(): string
    {
        for ($attempt = 0; $attempt < self::RANDOM_ATTEMPTS; $attempt++) {
            $slug = ContentNode::BLOCK_PREFIX.$this->randomLetters(self::RANDOM_LENGTH);

            if (! DB::table('content_nodes')->where('slug', $slug)->exists()) {
                return $slug;
            }
        }

        throw new RuntimeException('Could not generate a unique block identifier.');
    }

    private function randomLetters(int $length): string
    {
        $letters = '';

        for ($i = 0; $i < $length; $i++) {
            $letters .= chr(random_int(ord('a'), ord('z')));
        }

        return $letters;
    }
}
