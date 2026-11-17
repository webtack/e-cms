<?php

namespace Webtack\Ecms\Tests\Feature\Content;

use Webtack\Ecms\Content\HtmlSanitizer;
use Webtack\Ecms\Tests\TestCase;

class HtmlSanitizerTest extends TestCase
{
    private function sanitizer(): HtmlSanitizer
    {
        return app(HtmlSanitizer::class);
    }

    public function test_script_with_its_content_is_removed(): void
    {
        $this->assertSame('<p>Hi</p>', $this->sanitizer()->clean('<p>Hi</p><script>alert(1)</script>'));
    }

    public function test_event_handler_attribute_is_removed(): void
    {
        $this->assertSame('<p>Hi</p>', $this->sanitizer()->clean('<p onclick="x()">Hi</p>'));
    }

    public function test_javascript_link_scheme_is_removed(): void
    {
        $clean = $this->sanitizer()->clean('<a href="javascript:alert(1)">x</a>');

        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringContainsString('x', $clean);
    }

    public function test_data_link_scheme_is_removed(): void
    {
        $this->assertStringNotContainsString('data:', $this->sanitizer()->clean('<a href="data:text/html;base64,PHNjcmlwdD4=">x</a>'));
    }

    public function test_image_tag_is_removed(): void
    {
        $clean = $this->sanitizer()->clean('<p>a<img src="https://example.com/a.png" onerror="x()">b</p>');

        $this->assertStringNotContainsString('<img', $clean);
        $this->assertSame('<p>ab</p>', $clean);
    }

    private const UUID = '0190a1b27c3e7f0a8b1c2d3e4f5a6b7c';

    public function test_image_from_the_storage_keeps_src_and_alt_without_handlers(): void
    {
        $clean = $this->sanitizer()->clean('<img src="/storage/'.self::UUID.'.png" alt="Лого" onerror="x()">');

        $this->assertStringContainsString('src="/storage/'.self::UUID.'.png"', $clean);
        $this->assertStringContainsString('alt="Лого"', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertSame('', $this->sanitizer()->text($clean));
        $this->assertTrue($this->sanitizer()->hasImage($clean));
    }

    public function test_image_with_a_foreign_address_is_removed(): void
    {
        foreach ([
            'http://localhost/storage/'.self::UUID.'.png',
            '//host/storage/'.self::UUID.'.png',
            'data:image/png;base64,AAAA',
            'https://evil.example/a.png',
            '/other/'.self::UUID.'.png',
            '/storage/'.self::UUID.'.png?x=1',
            '/storage/../'.self::UUID.'.png',
        ] as $src) {
            $clean = $this->sanitizer()->clean('<p>a<img src="'.$src.'">b</p>');

            $this->assertSame('<p>ab</p>', $clean, $src);
            $this->assertFalse($this->sanitizer()->hasImage($clean), $src);
        }
    }

    public function test_image_without_src_is_removed(): void
    {
        $this->assertSame('<p>a</p>', $this->sanitizer()->clean('<p>a<img alt="x"></p>'));
    }

    public function test_image_width_is_kept_only_as_a_whole_number_in_range(): void
    {
        $src = '/storage/'.self::UUID.'.png';

        $kept = $this->sanitizer()->clean('<img src="'.$src.'" width="200" height="100">');
        $this->assertStringContainsString('width="200"', $kept);
        $this->assertStringNotContainsString('height', $kept);

        foreach (['5000', '10px', '0', '1.5', '-3', ''] as $width) {
            $clean = $this->sanitizer()->clean('<img src="'.$src.'" width="'.$width.'">');

            $this->assertStringContainsString('<img', $clean, $width);
            $this->assertStringNotContainsString('width', $clean, $width);
        }
    }

    public function test_figure_keeps_the_image_and_caption_without_attributes(): void
    {
        $clean = $this->sanitizer()->clean('<figure class="x" onclick="y()"><img src="/storage/'.self::UUID.'.png"><figcaption id="z">Офіс</figcaption></figure>');

        $this->assertStringStartsWith('<figure><img', $clean);
        $this->assertStringContainsString('<figcaption>Офіс</figcaption></figure>', $clean);
        $this->assertStringNotContainsString('class', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertSame('Офіс', $this->sanitizer()->text($clean));
    }

    public function test_figure_keeps_only_text_align_style(): void
    {
        $img = '<img src="/storage/'.self::UUID.'.png">';

        $this->assertStringStartsWith('<figure style="text-align: center">', $this->sanitizer()->clean('<figure style="text-align: center">'.$img.'</figure>'));
        $this->assertStringStartsWith('<figure style="text-align: right"><img', $this->sanitizer()->clean('<figure style="text-align: right">'.$img.'<figcaption>Офіс</figcaption></figure>'));
        $this->assertStringStartsWith('<figure style="text-align: center"><img', $this->sanitizer()->clean('<figure style="position: absolute; color: #f00; text-align: center">'.$img.'</figure>'));
        $this->assertStringStartsWith('<figure><img', $this->sanitizer()->clean('<figure style="color: #f00">'.$img.'</figure>'));
    }

    public function test_figure_keeps_pixel_width_and_auto_margins(): void
    {
        $img = '<img src="/storage/'.self::UUID.'.png">';

        $this->assertStringStartsWith(
            '<figure style="width: 500px; max-width: 100%; margin-left: auto; margin-right: auto; text-align: center"><img',
            $this->sanitizer()->clean('<figure style="width: 500px; max-width: 100%; margin-left: auto; margin-right: auto; text-align: center">'.$img.'</figure>')
        );
        $this->assertStringStartsWith('<figure style="width: fit-content; max-width: 100%; margin-left: auto; text-align: right"><img', $this->sanitizer()->clean('<figure style="width: fit-content; max-width: 100%; margin-left: auto; text-align: right">'.$img.'</figure>'));
        // `min-content` is still kept for records saved earlier.
        $this->assertStringStartsWith('<figure style="width: min-content; text-align: center"><img', $this->sanitizer()->clean('<figure style="width: min-content; text-align: center">'.$img.'</figure>'));
        $this->assertStringStartsWith('<figure style="margin-left: 0"><img', $this->sanitizer()->clean('<figure style="margin-left: 0">'.$img.'</figure>'));
    }

    public function test_figure_drops_other_widths_margins_and_position(): void
    {
        $img = '<img src="/storage/'.self::UUID.'.png">';

        $this->assertStringStartsWith('<figure><img', $this->sanitizer()->clean('<figure style="width: 9999px; margin-left: 50px; margin-right: 1em; position: absolute">'.$img.'</figure>'));
        $this->assertStringStartsWith('<figure><img', $this->sanitizer()->clean('<figure style="width: 4001px; max-width: 50%">'.$img.'</figure>'));
        $this->assertStringStartsWith('<figure style="margin-left: auto"><img', $this->sanitizer()->clean('<figure style="margin-left: auto; margin-top: 5px; width: 50%">'.$img.'</figure>'));
        // `width` and margins are for the figure only.
        $this->assertStringNotContainsString('style', $this->sanitizer()->clean('<p style="width: 200px; max-width: 100%; margin-left: auto">x</p>'));
    }

    public function test_image_keeps_no_style(): void
    {
        $this->assertStringNotContainsString('style', $this->sanitizer()->clean('<img src="/storage/'.self::UUID.'.png" style="text-align: center">'));
    }

    public function test_figure_without_an_image_is_not_content(): void
    {
        $this->assertFalse($this->sanitizer()->hasImage('<figure><figcaption>x</figcaption></figure>'));
    }

    public function test_safe_link_keeps_its_address_and_gets_rel(): void
    {
        $clean = $this->sanitizer()->clean('<a href="https://example.com">x</a>');

        $this->assertStringContainsString('href="https://example.com"', $clean);
        $this->assertStringContainsString('rel="noopener noreferrer nofollow"', $clean);
    }

    public function test_mailto_tel_and_relative_links_are_kept(): void
    {
        $clean = $this->sanitizer()->clean('<a href="mailto:a@b.co">m</a><a href="tel:+380">t</a><a href="/about">r</a>');

        $this->assertStringContainsString('href="mailto:a&#64;b.co"', $clean);
        $this->assertStringContainsString('href="tel:&#43;380"', $clean);
        $this->assertStringContainsString('href="/about"', $clean);
    }

    public function test_allowed_formatting_is_kept(): void
    {
        $html = '<h2>T</h2><p>a <strong>b</strong> <em>c</em><br />d</p><ul><li>x</li></ul><ol><li>y</li></ol><blockquote><p>q</p></blockquote>';

        $this->assertSame($html, $this->sanitizer()->clean($html));
    }

    public function test_text_of_a_dropped_tag_is_kept(): void
    {
        $this->assertSame('<p>Hello world</p>', $this->sanitizer()->clean('<p>Hello <font>world</font></p>'));
    }

    public function test_text_align_is_kept(): void
    {
        $this->assertSame('<p style="text-align: center">Hi</p>', $this->sanitizer()->clean('<p style="text-align: center">Hi</p>'));
    }

    public function test_color_and_background_are_kept(): void
    {
        $clean = $this->sanitizer()->clean('<span style="color: #ff0000; background-color: #ffff00">x</span>');

        $this->assertStringContainsString('color: #ff0000', $clean);
        $this->assertStringContainsString('background-color: #ffff00', $clean);
        $this->assertStringStartsWith('<span', $clean);
    }

    public function test_rgb_color_is_kept(): void
    {
        $this->assertStringContainsString('color: rgb(1, 2, 3)', $this->sanitizer()->clean('<span style="color: rgb(1, 2, 3)">x</span>'));
    }

    public function test_unsafe_style_is_cleaned(): void
    {
        $clean = $this->sanitizer()->clean('<span style="color: red; position: fixed; background-color: url(javascript:x)">x</span>');

        $this->assertStringNotContainsString('position', $clean);
        $this->assertStringNotContainsString('url(', $clean);
        $this->assertStringContainsString('x', $clean);
        $this->assertStringNotContainsString('style=', $clean);
    }

    public function test_only_safe_declarations_survive_next_to_unsafe_ones(): void
    {
        $clean = $this->sanitizer()->clean('<span style="position: fixed; color: #f00">x</span>');

        $this->assertStringContainsString('style="color: #f00"', $clean);
        $this->assertStringNotContainsString('position', $clean);
    }

    public function test_style_on_a_disallowed_tag_is_removed(): void
    {
        $this->assertSame('<strong>x</strong>', $this->sanitizer()->clean('<strong style="color: #f00">x</strong>'));
    }

    public function test_frame_from_an_allowed_host_is_kept_with_sandbox(): void
    {
        $clean = $this->sanitizer()->clean('<iframe src="https://www.youtube-nocookie.com/embed/abc" onload="x()"></iframe>');

        $this->assertStringContainsString('src="https://www.youtube-nocookie.com/embed/abc"', $clean);
        $this->assertStringContainsString('sandbox="', $clean);
        $this->assertStringContainsString('loading="lazy"', $clean);
        $this->assertStringNotContainsString('onload', $clean);
    }

    public function test_frame_attributes_are_limited(): void
    {
        $clean = $this->sanitizer()->clean('<iframe src="https://player.vimeo.com/video/1" width="560" height="315" title="T" frameborder="0" style="x:y" allowfullscreen></iframe>');

        $this->assertStringContainsString('width="560"', $clean);
        $this->assertStringContainsString('height="315"', $clean);
        $this->assertStringContainsString('title="T"', $clean);
        $this->assertStringContainsString('allowfullscreen', $clean);
        $this->assertStringNotContainsString('frameborder', $clean);
        $this->assertStringNotContainsString('style=', $clean);
    }

    public function test_frame_allow_attribute_is_cleaned(): void
    {
        $clean = $this->sanitizer()->clean('<iframe src="https://www.youtube-nocookie.com/embed/abc" allow="autoplay; camera; picture-in-picture"></iframe>');

        $this->assertStringContainsString('allow="autoplay; picture-in-picture"', $clean);
        $this->assertStringNotContainsString('camera', $clean);
    }

    public function test_google_drive_forms_and_facebook_plugin_frames_are_kept(): void
    {
        foreach ([
            'https://drive.google.com/file/d/abc/preview',
            'https://docs.google.com/forms/d/e/abc/viewform?embedded=true',
            'https://www.facebook.com/plugins/post.php?href=x',
        ] as $src) {
            $clean = $this->sanitizer()->clean('<iframe src="'.$src.'"></iframe>');

            $this->assertStringContainsString('<iframe', $clean, $src);
            $this->assertStringContainsString('sandbox="', $clean, $src);
            $this->assertStringContainsString('loading="lazy"', $clean, $src);
        }
    }

    public function test_google_maps_embed_frame_is_kept_and_other_map_pages_are_removed(): void
    {
        $embed = $this->sanitizer()->clean('<iframe src="https://www.google.com/maps/embed?pb=!1m18"></iframe>');

        $this->assertStringContainsString('<iframe', $embed);
        $this->assertStringContainsString('sandbox="', $embed);
        $this->assertSame('', $this->sanitizer()->clean('<iframe src="https://www.google.com/search?q=x"></iframe>'));
    }

    public function test_frame_outside_the_path_prefix_is_removed(): void
    {
        $this->assertSame('', $this->sanitizer()->clean('<iframe src="https://www.facebook.com/some.page"></iframe>'));
    }

    public function test_frame_from_a_foreign_host_or_not_https_is_removed(): void
    {
        foreach ([
            'https://evil.example/x',
            'http://www.youtube.com/embed/a',
            'https://youtube.com.evil.example/embed/a',
            'https://www.youtube.com.evil.example/embed/a',
            'https://user@www.youtube-nocookie.com/embed/a',
            '//www.youtube-nocookie.com/embed/a',
            'javascript:alert(1)',
            'data:text/html;base64,PHNjcmlwdD4=',
        ] as $src) {
            $this->assertStringNotContainsString('<iframe', $this->sanitizer()->clean('<p>a</p><iframe src="'.$src.'"></iframe>'), $src);
        }
    }

    public function test_frame_path_with_dot_segments_or_encoded_separators_is_removed(): void
    {
        foreach ([
            'https://www.youtube.com/embed/../account',
            'https://www.youtube.com/embed/%2e%2e/account',
            'https://www.youtube.com/embed/%2E%2E/x',
            'https://www.youtube.com/embed/a%2fb',
            'https://www.youtube.com/embed/a%5cb',
            'https://www.youtube.com/embed/./a',
        ] as $src) {
            $this->assertSame('', $this->sanitizer()->clean('<iframe src="'.$src.'"></iframe>'), $src);
        }
    }

    public function test_link_with_a_protocol_relative_or_backslash_address_loses_its_href(): void
    {
        foreach (['//example.com/a', '/\\example.com'] as $href) {
            $clean = $this->sanitizer()->clean('<a href="'.$href.'">x</a>');

            $this->assertStringNotContainsString('href', $clean, $href);
            $this->assertStringContainsString('x', $clean, $href);
        }
    }

    public function test_ordinary_link_addresses_are_kept(): void
    {
        foreach (['/about', '#top', '?page=2', 'https://example.com/a', 'mailto:info@example.com', 'tel:+380441234567'] as $href) {
            $this->assertStringContainsString('href="', $clean = $this->sanitizer()->clean('<a href="'.$href.'">x</a>'), $href);
            $this->assertStringContainsString(htmlspecialchars_decode(html_entity_decode($href)), html_entity_decode($clean), $href);
        }
    }

    public function test_frame_without_src_is_removed(): void
    {
        $this->assertSame('<p>a</p>', $this->sanitizer()->clean('<p>a</p><iframe title="x src=y"></iframe>'));
    }

    public function test_text_returns_visible_text_without_tags(): void
    {
        $this->assertSame('Привіт, світе', $this->sanitizer()->text('<p>Привіт, <strong>світе</strong></p>'));
    }

    public function test_text_decodes_entities_and_separates_blocks(): void
    {
        $this->assertSame('a & b c', $this->sanitizer()->text('<p>a &amp; b</p><p>c</p>'));
    }

    public function test_text_of_empty_paragraph_is_empty(): void
    {
        $this->assertSame('', $this->sanitizer()->text('<p></p>'));
        $this->assertSame('', $this->sanitizer()->text('<p>&nbsp;</p>'));
    }

    public function test_pasted_bold_and_italic_are_kept_without_attributes(): void
    {
        $this->assertSame('<p><b>Bold</b> and <i>italic</i></p>', $this->sanitizer()->clean('<p><b>Bold</b> and <i>italic</i></p>'));
        $this->assertSame('<b>A</b><i>B</i>', $this->sanitizer()->clean('<b style="color:red" class="z" onclick="x()">A</b><i class="y">B</i>'));
    }

    public function test_a_table_keeps_its_structure(): void
    {
        $html = '<table><thead><tr><th>Time</th><th>Event</th></tr></thead><tbody><tr><td>10:00</td><td>Opening</td></tr></tbody><tfoot><tr><td>End</td><td>.</td></tr></tfoot></table>';

        $this->assertSame($html, $this->sanitizer()->clean($html));
    }

    public function test_cell_spans_are_kept_as_whole_numbers(): void
    {
        $clean = $this->sanitizer()->clean('<table><tr><td colspan="3" rowspan="2">A</td><th colspan="03">B</th><td colspan="100">C</td></tr></table>');

        $this->assertStringContainsString('<td colspan="3" rowspan="2">A</td>', $clean);
        $this->assertStringContainsString('<th colspan="3">B</th>', $clean);
        $this->assertStringContainsString('<td colspan="100">C</td>', $clean);
    }

    public function test_a_wrong_span_is_removed_and_the_cell_stays(): void
    {
        $clean = $this->sanitizer()->clean('<table><tr><td colspan="0" rowspan="x">A</td><td colspan="500">B</td><td colspan="101">C</td><td colspan="1.5">D</td><td colspan="">E</td></tr></table>');

        $this->assertStringNotContainsString('colspan', $clean);
        $this->assertStringNotContainsString('rowspan', $clean);

        foreach (['A', 'B', 'C', 'D', 'E'] as $text) {
            $this->assertStringContainsString("<td>{$text}</td>", $clean);
        }
    }

    public function test_pasted_widths_and_styles_of_a_table_are_removed(): void
    {
        $clean = $this->sanitizer()->clean('<table style="width:400pt" width="400" class="x" onclick="y()"><tbody><tr><td style="width:175.2pt" width="234" valign="top" class="c" onclick="z()"><p style="text-align:center">A</p></td></tr></tbody></table>');

        $this->assertSame('<table><tbody><tr><td><p style="text-align: center">A</p></td></tr></tbody></table>', $clean);
    }

    public function test_caption_and_column_groups_are_removed_and_the_caption_text_stays(): void
    {
        $clean = $this->sanitizer()->clean('<table><caption>Results</caption><colgroup><col style="width:50%"></colgroup><tr><td>A</td></tr></table>');

        foreach (['<caption', '<colgroup', '<col'] as $tag) {
            $this->assertStringNotContainsString($tag, $clean);
        }

        $this->assertStringContainsString('Results', $clean);
        $this->assertStringContainsString('<td>A</td>', $clean);
        $this->assertStringContainsString('<table>', $clean);
    }

    public function test_the_output_of_the_editor_cleans_to_the_plain_structure(): void
    {
        $this->assertSame(
            '<table><tbody><tr><th><p>A</p></th></tr></tbody></table>',
            $this->sanitizer()->clean('<table style="min-width: 75px"><colgroup><col style="width:25px"></colgroup><tbody><tr><th colwidth="25"><p>A</p></th></tr></tbody></table>'),
        );
    }

    public function test_table_cells_and_rows_do_not_merge_in_the_visible_text(): void
    {
        $this->assertSame('10:00 Opening', $this->sanitizer()->text('<table><tr><td>10:00</td><td>Opening</td></tr></table>'));
        $this->assertSame('A B C D', $this->sanitizer()->text('<table><tr><td>A</td><td>B</td></tr><tr><td>C</td><td>D</td></tr></table>'));
        $this->assertSame('', $this->sanitizer()->text('<table><tr><td></td><td></td></tr></table>'));
    }

    public function test_a_long_text_is_not_cut_at_the_library_default(): void
    {
        $html = '<table><tbody>'.str_repeat('<tr><td>abcdefghij</td><td>klmnop</td></tr>', 1500).'</tbody></table>';

        $this->assertSame(1500, substr_count($this->sanitizer()->clean($html), '<tr>'));
    }

    public function test_the_paragraph_of_a_simple_list_item_is_unwrapped(): void
    {
        $this->assertSame('<ul><li>One</li><li>Two</li></ul>', $this->sanitizer()->clean('<ul><li><p>One</p></li><li><p>Two</p></li></ul>'));
        $this->assertSame('<ol><li>One</li></ol>', $this->sanitizer()->clean('<ol><li><p>One</p></li></ol>'));
    }

    public function test_inline_formatting_of_a_list_item_is_kept_when_its_paragraph_is_unwrapped(): void
    {
        $clean = $this->sanitizer()->clean('<ul><li><p>A <strong>bold</strong>, <em>italic</em>, <a href="https://example.com">link</a>, <span style="color: #dc2626">red</span><br>text</p></li></ul>');

        $this->assertStringStartsWith('<ul><li>A <strong>bold</strong>, <em>italic</em>, <a href="https://example.com"', $clean);
        $this->assertStringContainsString('>link</a>, <span style="color: #dc2626">red</span><br>text</li></ul>', $clean);
        $this->assertStringNotContainsString('<p>', $clean);
    }

    public function test_a_nested_list_stays_inside_the_unwrapped_item(): void
    {
        $this->assertSame(
            '<ul><li>One<ul><li>Inner</li></ul></li></ul>',
            $this->sanitizer()->clean('<ul><li><p>One</p><ul><li><p>Inner</p></li></ul></li></ul>'),
        );
    }

    public function test_several_paragraphs_of_a_list_item_stay(): void
    {
        $this->assertSame('<ul><li><p>One</p><p>Two</p></li></ul>', $this->sanitizer()->clean('<ul><li><p>One</p><p>Two</p></li></ul>'));
    }

    public function test_an_aligned_paragraph_of_a_list_item_stays(): void
    {
        $this->assertSame(
            '<ol><li><p style="text-align: center">One</p></li></ol>',
            $this->sanitizer()->clean('<ol><li><p style="text-align: center">One</p></li></ol>'),
        );
    }

    public function test_a_paragraph_next_to_loose_text_in_a_list_item_stays(): void
    {
        $this->assertSame('<ul><li>Loose<p>Para</p></li></ul>', $this->sanitizer()->clean('<ul><li>Loose<p>Para</p></li></ul>'));
    }

    public function test_the_visible_text_does_not_depend_on_the_paragraphs_in_list_items(): void
    {
        $this->assertSame(
            $this->sanitizer()->text('<ul><li>One</li><li>Two<ul><li>In</li></ul></li></ul>'),
            $this->sanitizer()->text('<ul><li><p>One</p></li><li><p>Two</p><ul><li><p>In</p></li></ul></li></ul>'),
        );
        $this->assertSame('One Two In', $this->sanitizer()->text('<ul><li><p>One</p></li><li><p>Two</p><ul><li><p>In</p></li></ul></li></ul>'));
    }

    public function test_an_already_clean_value_stays_the_same(): void
    {
        foreach (['<ul><li>One</li></ul>', '<p>Text</p><ul><li>One<ul><li>In</li></ul></li></ul>', '<p>No list &amp; <strong>marks</strong></p>'] as $html) {
            $this->assertSame($html, $this->sanitizer()->clean($html));
            $this->assertSame($html, $this->sanitizer()->clean($this->sanitizer()->clean($html)));
        }
    }
}
