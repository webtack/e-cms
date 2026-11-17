<?php

namespace Webtack\Ecms\Content;

/**
 * Finds a search term in a plain text and splits the text around its occurrences, so a template can highlight them
 * without printing raw HTML.
 *
 * The comparison is literal (`%`, `_`, `.`, `(` and other regular-expression characters have no special meaning),
 * case-insensitive and Unicode-aware. It does not fold accents: a collation-only match of the database is not found
 * here. Both methods use the same comparison, so choosing a text and highlighting it always agree.
 *
 * @example
 *   TermHighlighter::contains('Lamp museum', 'lamp'); // true
 *   TermHighlighter::parts('Lamp museum of the lamp', 'lamp');
 *   // [
 *   //   ['text' => 'Lamp', 'match' => true],
 *   //   ['text' => ' museum of the ', 'match' => false],
 *   //   ['text' => 'lamp', 'match' => true],
 *   // ]
 */
final class TermHighlighter
{
    /** Whether the text contains the term (literal, case-insensitive). False for an empty term or invalid UTF-8. */
    public static function contains(string $text, string $term): bool
    {
        if ($text === '' || $term === '') {
            return false;
        }

        return preg_match(self::pattern($term), $text) === 1;
    }

    /**
     * Splits the text into consecutive parts; every occurrence of the term is a separate part with `match` true and
     * the text as written in the source. The parts joined give back the text exactly. With no occurrence, an empty
     * term, or invalid UTF-8, the result is one unflagged part (none for an empty text).
     *
     * @return list<array{text: string, match: bool}>
     */
    public static function parts(string $text, string $term): array
    {
        if ($text === '') {
            return [];
        }

        if ($term === '' || preg_match_all(self::pattern($term), $text, $found, PREG_OFFSET_CAPTURE) === false || $found[0] === []) {
            return [['text' => $text, 'match' => false]];
        }

        $parts = [];
        $position = 0;

        foreach ($found[0] as [$match, $offset]) {
            if ($offset > $position) {
                $parts[] = ['text' => substr($text, $position, $offset - $position), 'match' => false];
            }

            $parts[] = ['text' => $match, 'match' => true];
            $position = $offset + strlen($match);
        }

        if ($position < strlen($text)) {
            $parts[] = ['text' => substr($text, $position), 'match' => false];
        }

        return $parts;
    }

    private static function pattern(string $term): string
    {
        return '/'.preg_quote($term, '/').'/iu';
    }
}
