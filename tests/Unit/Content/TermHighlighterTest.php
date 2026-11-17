<?php

namespace Webtack\Ecms\Tests\Unit\Content;

use PHPUnit\Framework\TestCase;
use Webtack\Ecms\Content\TermHighlighter;

class TermHighlighterTest extends TestCase
{
    public function test_every_occurrence_is_a_separate_flagged_part_and_the_case_of_the_text_is_kept(): void
    {
        $this->assertSame([
            ['text' => 'Lamp', 'match' => true],
            ['text' => ' museum of the ', 'match' => false],
            ['text' => 'lamp', 'match' => true],
        ], TermHighlighter::parts('Lamp museum of the lamp', 'LAMP'));
    }

    public function test_regular_expression_and_like_characters_are_literal(): void
    {
        $this->assertSame([
            ['text' => 'Save ', 'match' => false],
            ['text' => '100%', 'match' => true],
            ['text' => ' now', 'match' => false],
        ], TermHighlighter::parts('Save 100% now', '100%'));

        $this->assertSame([['text' => 'axb', 'match' => false]], TermHighlighter::parts('axb', 'a.b'));
        $this->assertSame([['text' => 'a.b', 'match' => true]], TermHighlighter::parts('a.b', 'a.b'));
        $this->assertSame([['text' => '(x', 'match' => true], ['text' => ')', 'match' => false]], TermHighlighter::parts('(x)', '(x'));
        $this->assertSame([['text' => 'a_b', 'match' => true]], TermHighlighter::parts('a_b', 'a_b'));
        $this->assertSame([['text' => 'a/b', 'match' => true]], TermHighlighter::parts('a/b', 'a/b'));
    }

    public function test_no_occurrence_gives_one_unflagged_part(): void
    {
        $this->assertSame([['text' => 'Museum', 'match' => false]], TermHighlighter::parts('Museum', 'lamp'));
    }

    public function test_an_empty_text_has_no_parts(): void
    {
        $this->assertSame([], TermHighlighter::parts('', 'lamp'));
    }

    public function test_multibyte_letters_are_matched_without_case(): void
    {
        $this->assertSame([
            ['text' => 'Музей ', 'match' => false],
            ['text' => 'ЛАМПИ', 'match' => true],
            ['text' => ' та лампа', 'match' => false],
        ], TermHighlighter::parts('Музей ЛАМПИ та лампа', 'лампи'));
    }

    public function test_invalid_utf8_falls_back_to_one_unflagged_part(): void
    {
        $text = "lamp \xB1 lamp";

        $this->assertSame([['text' => $text, 'match' => false]], TermHighlighter::parts($text, 'lamp'));
        $this->assertFalse(TermHighlighter::contains($text, 'lamp'));
    }

    public function test_parts_joined_equal_the_text(): void
    {
        $text = 'Ламп lamp, <b>LAMP</b> & lamps — Lamp';

        $this->assertSame($text, implode('', array_column(TermHighlighter::parts($text, 'lamp'), 'text')));
    }

    public function test_contains_is_literal_and_case_insensitive(): void
    {
        $this->assertTrue(TermHighlighter::contains('Lamp museum', 'lAMP'));
        $this->assertTrue(TermHighlighter::contains('a.b', 'a.b'));
        $this->assertFalse(TermHighlighter::contains('axb', 'a.b'));
        $this->assertFalse(TermHighlighter::contains('', 'lamp'));
        $this->assertFalse(TermHighlighter::contains('lamp', ''));
    }
}
