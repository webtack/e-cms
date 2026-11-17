<?php

namespace Webtack\Ecms\Tests\Feature\Theme;

use Webtack\Ecms\Tests\TestCase;
use Webtack\Ecms\Theme\MenuActive;

/**
 * The matching rules of {@see MenuActive}: segment boundary, longest match, home, query, items that never match.
 */
class MenuActiveTest extends TestCase
{
    /** @return array<string, mixed> */
    private function item(?string $url, array $children = []): array
    {
        return ['url' => $url, 'children' => $children];
    }

    /** The flags as a flat `url => 'A'|'T'|'-'` map (null urls are keyed by position). */
    private function flags(array $items, array &$out = []): array
    {
        foreach ($items as $i => $item) {
            $out[($item['url'] ?? 'none'.count($out))] = $item['is_active'] ? 'A' : ($item['is_trail'] ? 'T' : '-');
            $this->flags($item['children'], $out);
        }

        return $out;
    }

    public function test_the_nested_item_is_active_and_its_parent_is_in_the_trail(): void
    {
        $tree = [$this->item('/catalog', [$this->item('/catalog/jackets')]), $this->item('/about')];

        $this->assertSame(
            ['/catalog' => 'T', '/catalog/jackets' => 'A', '/about' => '-'],
            $this->flags(MenuActive::mark($tree, '/catalog/jackets')),
        );
    }

    public function test_a_prefix_matches_only_on_a_segment_boundary(): void
    {
        $tree = [$this->item('/news')];

        $this->assertSame(['/news' => 'A'], $this->flags(MenuActive::mark($tree, '/news/hello')));
        $this->assertSame(['/news' => '-'], $this->flags(MenuActive::mark($tree, '/newsletter')));
    }

    public function test_the_longest_match_wins(): void
    {
        $tree = [$this->item('/news'), $this->item('/news/culture')];

        $this->assertSame(
            ['/news' => '-', '/news/culture' => 'A'],
            $this->flags(MenuActive::mark($tree, '/news/culture/today')),
        );
    }

    public function test_equal_paths_pick_the_first_in_menu_order(): void
    {
        $tree = [$this->item('/a', [$this->item('/a/b')]), $this->item('/a/b/')];
        $marked = MenuActive::mark($tree, '/a/b');

        $this->assertTrue($marked[0]['children'][0]['is_active']);
        $this->assertFalse($marked[1]['is_active']);
    }

    public function test_the_home_address_matches_only_the_home_page(): void
    {
        $tree = [$this->item('/')];

        $this->assertSame(['/' => '-'], $this->flags(MenuActive::mark($tree, '/about')));
        $this->assertSame(['/' => 'A'], $this->flags(MenuActive::mark($tree, '/')));
    }

    public function test_query_fragment_and_trailing_slash_are_ignored(): void
    {
        $tree = [$this->item('/news/?x=1#top')];

        $this->assertSame(['/news/?x=1#top' => 'A'], $this->flags(MenuActive::mark($tree, '/news?page=2')));
        $this->assertSame(['/news/?x=1#top' => 'A'], $this->flags(MenuActive::mark($tree, '/news/')));
    }

    public function test_items_without_a_matching_address_never_match(): void
    {
        $tree = [
            $this->item(null),
            $this->item('https://example.org/news'),
            $this->item('mailto:a@b.c'),
            $this->item('#top'),
            $this->item('?page=1'),
        ];

        foreach (MenuActive::mark($tree, '/news') as $item) {
            $this->assertFalse($item['is_active']);
            $this->assertFalse($item['is_trail']);
        }
    }

    public function test_every_item_gets_both_flags_when_nothing_matches(): void
    {
        $marked = MenuActive::mark([$this->item('/a', [$this->item('/a/b')])], '/zzz');

        $this->assertFalse($marked[0]['is_active']);
        $this->assertFalse($marked[0]['is_trail']);
        $this->assertFalse($marked[0]['children'][0]['is_active']);
        $this->assertFalse($marked[0]['children'][0]['is_trail']);
    }
}
