<?php

namespace Webtack\Ecms\Content;

use Dom\Element;
use Dom\HTMLDocument;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer as SymfonySanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Cleans HTML of rich text values against an allow-list and extracts its visible text.
 *
 * Allowed: headings (h2-h4), paragraphs, line breaks, emphasis (`strong`, `em`, and pasted `b` / `i` kept as written,
 * without attributes), blockquotes, lists, tables (`table`, `thead`, `tbody`, `tfoot`, `tr`, `th`, `td`; on `th` and `td`
 * only `colspan` and `rowspan` as whole numbers from 1 to 100, see {@see TableSpanSanitizer}; `caption`, `colgroup` and `col`
 * are unwrapped, so the text of a caption stays), `span` and links
 * (`href` with `http`, `https`, `mailto`, `tel` or a relative address; `rel` is forced). `style` is kept
 * only on text blocks, `span` and `figure` (there `text-align`, `width`, `max-width: 100%` and the side margins), and only `text-align`, `color` and `background-color` with safe values
 * (see {@see StyleAttributeSanitizer}). An `iframe` stays only with an https `src` from the hosts of
 * `content.richtext.iframe_hosts` (see {@see IframeSrcSanitizer}), a cleaned `allow`, and a forced `sandbox`
 * and `loading="lazy"`; otherwise the whole tag goes. An `img` stays only with `src`, `alt` and `width`, and only
 * when `src` is the relative address of a stored file (see {@see ImageSrcSanitizer}) and `width` is a whole
 * number of pixels (see {@see ImageWidthSanitizer}); `figure` (an image with a caption or an
 * alignment) keeps `style` with `text-align`, `width` (`Npx`, `fit-content`, `min-content`), `max-width: 100%` and `margin-left` / `margin-right` (`auto` or `0`) only and `figcaption` (the caption) keeps no attributes. Every other tag, attribute and event handler is
 * dropped; the text left after dropping a tag is kept, except the content of `script` and `style`.
 * A list item keeps its text directly: the only attribute-less `p` of an `li` is unwrapped (`<li><p>One</p></li>` becomes
 * `<li>One</li>`; several paragraphs, an aligned paragraph, or a `p` next to loose text stay as they are), see {@see self::unwrapListParagraphs()}.
 * Input is read up to `content.richtext.max_input_length` characters (the library's own default of 20,000 would cut a long text).
 * This is the only trusted sanitising layer: the client is not.
 *
 * @example
 *   $sanitizer = app(HtmlSanitizer::class);
 *   $sanitizer->clean('<p onclick="x()">Hi <img src="a.png"></p>');   // '<p>Hi </p>' (an image outside the storage goes)
 *   $sanitizer->text('<p>Hi&nbsp;<strong>there</strong></p>');        // 'Hi there'
 *   $sanitizer->clean('<ul><li><p>One</p></li></ul>');                // '<ul><li>One</li></ul>'
 */
class HtmlSanitizer
{
    /** Tags that may stay in the cleaned HTML. */
    private const ALLOWED_TAGS = ['p', 'br', 'h2', 'h3', 'h4', 'strong', 'em', 'u', 's', 'blockquote', 'ul', 'ol', 'li', 'span', 'figure', 'figcaption', 'b', 'i', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td'];

    /** Tags that are unwrapped: the tag goes, its text stays (pasted `div`, `font`, headings outside h2-h4, a table `caption` ...). */
    private const UNWRAPPED_TAGS = ['div', 'section', 'article', 'header', 'footer', 'main', 'font', 'h1', 'h5', 'h6', 'pre', 'code', 'caption', 'colgroup', 'col'];

    private ?SymfonySanitizer $sanitizer = null;

    /**
     * Remove everything that is not on the allow-list from an HTML fragment.
     */
    public function clean(string $html): string
    {
        return $this->unwrapListParagraphs($this->dropSourcelessImages($this->dropSourcelessFrames($this->sanitizer()->sanitize($html))));
    }

    /**
     * The visible text of an HTML fragment: no tags, entities decoded, blocks, table cells and rows separated by a space.
     */
    public function text(string $html): string
    {
        // A nested list opens right after the text of its item (`<li>Two<ul>`) once the item's `p` is unwrapped, so its start separates too.
        $spaced = preg_replace('/<\/(p|h[1-6]|li|blockquote|figcaption|td|th|tr)>|<br\s*\/?>|<(?:ul|ol)\b[^>]*>/i', ' ', $this->clean($html));
        $text = html_entity_decode(strip_tags((string) $spaced), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // A no-break space is not a visible character, so a paragraph holding only it counts as empty.
        $text = str_replace("\u{00A0}", ' ', $text);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * Unwrap the paragraph of a list item: `<li><p>One</p></li>` becomes `<li>One</li>`.
     *
     * The editor always serializes the text of an item inside a `p`, which themes then have to undo. A paragraph is
     * unwrapped only when it is the single `p` child of its `li`, has no attributes (an alignment `style` must stay)
     * and the `li` has no loose text next to it; its children (inline markup, a nested list) move into the `li`
     * unchanged, so the visible text is the same. A DOM pass (not a regex) keeps nested lists and attributes right;
     * markup without a list item is returned untouched.
     *
     * @example
     *   $this->unwrapListParagraphs('<ul><li><p>One</p><ul><li><p>In</p></li></ul></li></ul>');
     *   // '<ul><li>One<ul><li>In</li></ul></li></ul>'
     */
    private function unwrapListParagraphs(string $html): string
    {
        if (! preg_match('/<li\b[^>]*>\s*<p\b/i', $html)) {
            return $html;
        }

        $document = HTMLDocument::createFromString('<!DOCTYPE html><body>'.$html, LIBXML_NOERROR);

        /** @var Element $paragraph */
        foreach ($document->querySelectorAll('li > p') as $paragraph) {
            $item = $paragraph->parentNode;
            $paragraphs = 0;
            $loose = false;

            foreach ($item->childNodes as $child) {
                if ($child instanceof Element && $child->localName === 'p') {
                    $paragraphs++;
                } elseif ($child->nodeType === XML_TEXT_NODE && trim($child->textContent) !== '') {
                    $loose = true;
                }
            }

            if ($paragraphs !== 1 || $loose || $paragraph->attributes->length > 0) {
                continue;
            }

            while ($paragraph->firstChild) {
                $item->insertBefore($paragraph->firstChild, $paragraph);
            }
            $item->removeChild($paragraph);
        }

        return $document->body->innerHTML;
    }

    /**
     * Remove an `iframe` whose `src` was rejected (an empty frame is never wanted).
     */
    private function dropSourcelessFrames(string $html): string
    {
        return (string) preg_replace_callback(
            '/<iframe\b((?:\s+[a-z-]+(?:="[^"]*")?)*)\s*><\/iframe>/i',
            fn (array $match) => str_contains($match[1], ' src="') ? $match[0] : '',
            $html,
        );
    }

    /**
     * Remove an `img` whose `src` was rejected (an image without an address is never wanted).
     */
    private function dropSourcelessImages(string $html): string
    {
        return (string) preg_replace_callback(
            '/<img\b((?:\s+[a-z-]+(?:="[^"]*")?)*)\s*\/?>/i',
            fn (array $match) => str_contains($match[1], ' src="') ? $match[0] : '',
            $html,
        );
    }

    /**
     * Whether the cleaned HTML keeps at least one image (an image is content though it has no text).
     */
    public function hasImage(string $html): bool
    {
        return str_contains($this->clean($html), '<img');
    }

    /**
     * Whether the cleaned HTML keeps at least one embedded frame (a frame is content though it has no text).
     */
    public function hasFrame(string $html): bool
    {
        return str_contains($this->clean($html), '<iframe');
    }

    private function sanitizer(): SymfonySanitizer
    {
        if ($this->sanitizer === null) {
            $config = (new HtmlSanitizerConfig)->withMaxInputLength((int) config('content.richtext.max_input_length', 1_000_000));

            foreach (self::ALLOWED_TAGS as $tag) {
                $config = $config->allowElement($tag);
            }

            foreach (self::UNWRAPPED_TAGS as $tag) {
                $config = $config->blockElement($tag);
            }

            $this->sanitizer = new SymfonySanitizer(
                $config
                    ->allowElement('a', ['href'])
                    ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
                    ->allowRelativeLinks()
                    ->withAttributeSanitizer(new LinkHrefSanitizer)
                    ->forceAttribute('a', 'rel', 'noopener noreferrer nofollow')
                    ->allowElement('iframe', ['src', 'width', 'height', 'title', 'allowfullscreen', 'allow'])
                    ->forceAttribute('iframe', 'sandbox', 'allow-scripts allow-same-origin allow-presentation allow-popups')
                    ->forceAttribute('iframe', 'loading', 'lazy')
                    ->allowAttribute('style', ['span', 'p', 'h2', 'h3', 'h4', 'blockquote', 'li', 'figure'])
                    ->withAttributeSanitizer(new StyleAttributeSanitizer)
                    ->withAttributeSanitizer(new IframeSrcSanitizer)
                    ->withAttributeSanitizer(new IframeAllowSanitizer)
                    ->allowElement('img', ['src', 'alt', 'width'])
                    // Symfony's own URL check drops a relative `src`; ImageSrcSanitizer then keeps only the storage form.
                    ->allowRelativeMedias()
                    ->withAttributeSanitizer(new ImageSrcSanitizer)
                    ->withAttributeSanitizer(new ImageWidthSanitizer)
                    ->allowAttribute('colspan', ['th', 'td'])
                    ->allowAttribute('rowspan', ['th', 'td'])
                    ->withAttributeSanitizer(new TableSpanSanitizer)
            );
        }

        return $this->sanitizer;
    }
}
