import { mergeAttributes, Node } from '@tiptap/core';
import Image from '@tiptap/extension-image';
import { Table, TableCell, TableHeader, TableRow } from '@tiptap/extension-table';
import TextAlign from '@tiptap/extension-text-align';
import { BackgroundColor, Color, TextStyle } from '@tiptap/extension-text-style';
import Youtube from '@tiptap/extension-youtube';
import StarterKit from '@tiptap/starter-kit';

/**
 * A frame of an allowed host (Google Drive, a social plugin …). The server forces `sandbox` and `loading`, so the
 * node keeps only the address, the size and the title. A YouTube frame is left to the `Youtube` extension, which is
 * why `parseHTML` skips those addresses.
 */
export const Iframe = Node.create({
    name: 'iframe',
    group: 'block',
    atom: true,
    draggable: true,

    addAttributes() {
        return {
            src: { default: null },
            width: { default: null },
            height: { default: null },
            title: { default: null },
            allowfullscreen: { default: false, parseHTML: (element) => element.hasAttribute('allowfullscreen'), renderHTML: (attributes) => (attributes.allowfullscreen ? { allowfullscreen: '' } : {}) },
        };
    },

    parseHTML() {
        return [
            {
                tag: 'iframe[src]',
                // Priority below the default so the YouTube extension, which checks the host, wins for its addresses.
                priority: 40,
                getAttrs: (element) => (/(^|\.)youtube(-nocookie)?\.com\//i.test(element.getAttribute('src') ?? '') ? false : null),
            },
        ];
    },

    renderHTML({ HTMLAttributes }) {
        // The editor view sandboxes the frame too; the server forces the same attributes on save.
        return ['iframe', mergeAttributes({ 'data-rich-text-frame': '', sandbox: 'allow-scripts allow-same-origin allow-presentation allow-popups' }, HTMLAttributes)];
    },
});

/**
 * The YouTube extension renders `div[data-youtube-video] > iframe`, but the server drops the `div` (only the
 * `iframe` is stored), so a saved video is a bare `iframe`. This also reads that bare form back as a video.
 */
const YoutubeVideo = Youtube.extend({
    parseHTML() {
        return [{ tag: 'div[data-youtube-video] iframe' }, { tag: 'iframe[src*="youtube"]', priority: 60 }];
    },
});

/** The largest width (px) the server keeps on an image (`ImageWidthSanitizer`). */
export const IMAGE_MAX_WIDTH = 4000;

/** The width (px) a freshly inserted image gets, so a large picture does not stretch the text; "Original size" clears it. */
export const IMAGE_DEFAULT_WIDTH = 500;

/**
 * Reads the alignment of a `figure` from its side margins; a `figure` saved earlier with a plain `text-align` and no
 * margins still reads as centered or right.
 */
function figureAlign(figure: HTMLElement): 'left' | 'center' | 'right' {
    const { marginLeft, marginRight, textAlign, width } = figure.style;

    if (marginLeft === 'auto') {
        return marginRight === 'auto' ? 'center' : 'right';
    }

    if (!width && !marginLeft && (textAlign === 'center' || textAlign === 'right')) {
        return textAlign;
    }

    return 'left';
}

/**
 * An image of the media library: a block atom with `src` (the relative `/storage/{uuid}.{ext}` address), `alt`,
 * `width` (whole pixels, or `null` for the natural size), `caption` (plain text) and `align` (`left` by default, `center`
 * or `right`). It renders a bare `<img>`, or `<figure><img><figcaption>` when there is a caption or an alignment, and
 * reads both forms back, so the markup stays flat and no nested editable node is needed. The `figure` is as wide as the
 * image (`width: {width}px; max-width: 100%`, or `fit-content` for the natural size; `min-content` is avoided because the
 * image's percentage `max-width` makes it collapse to the longest caption word) and is aligned by its side margins
 * (`auto` on both for the center, on the left only for the right); the caption is centered inside it. The server
 * keeps exactly this markup (see `HtmlSanitizer`).
 *
 * @example
 * editor.commands.insertContent({ type: 'image', attrs: { src: '/storage/0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.png', alt: 'logo.png' } });
 * editor.getHTML();   // '<img src="/storage/0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.png" alt="logo.png">'
 * // with `align: 'center'`:
 * // '<figure style="width: 500px; max-width: 100%; margin-left: auto; margin-right: auto; text-align: center"><img src="/storage/0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.png" alt="logo.png" width="500"></figure>'
 */
export const RichImage = Image.extend({
    addAttributes() {
        return {
            src: { default: null },
            alt: { default: null },
            width: { default: null },
            caption: { default: null },
            align: { default: 'left' },
        };
    },

    parseHTML() {
        const read = (img: Element, caption: string | null, align: string = 'left') => ({
            src: img.getAttribute('src'),
            alt: img.getAttribute('alt'),
            width: img.getAttribute('width') ? Number(img.getAttribute('width')) : null,
            caption,
            align,
        });

        return [
            {
                tag: 'figure',
                priority: 60,
                getAttrs: (element) => {
                    const img = element.querySelector('img[src]');

                    return img ? read(img, element.querySelector('figcaption')?.textContent?.trim() || null, figureAlign(element as HTMLElement)) : false;
                },
            },
            { tag: 'img[src]', getAttrs: (element) => read(element, null) },
        ];
    },

    renderHTML({ node }) {
        const { src, alt, width, caption, align } = node.attrs;
        const img = ['img', { src, ...(alt ? { alt } : {}), ...(width ? { width } : {}) }] as const;

        if (!caption && align === 'left') {
            return img;
        }

        const margins = align === 'center' ? '; margin-left: auto; margin-right: auto' : align === 'right' ? '; margin-left: auto' : '';

        return ['figure', { style: `width: ${width ? `${width}px` : 'fit-content'}; max-width: 100%${margins}; text-align: center` }, img, ...(caption ? [['figcaption', {}, caption] as const] : [])];
    },
});

/**
 * The extensions of the rich text editor: exactly what the server allow-list keeps. Headings are h2-h4; there is no
 * code or horizontal rule; underline and strike come with the starter kit; alignment applies to paragraphs and
 * headings; color and background go through `TextStyle` as `span[style]`; YouTube uses the privacy-friendly host;
 * images come from the storage only (the toolbar uploads them), see `RichImage`. Tables are not resizable, so no
 * `colwidth` is kept; the markup is `table > tbody > tr > th|td` with `colspan` / `rowspan` only, which the server
 * keeps (`thead` / `tbody` / `tfoot` rows are read as the rows of one table; `colgroup` and `col` are stripped by the server).
 *
 * @example
 * const editor = useEditor({ extensions: richTextExtensions() });
 */
export function richTextExtensions() {
    return [
        StarterKit.configure({
            code: false,
            codeBlock: false,
            horizontalRule: false,
            trailingNode: false,
            heading: { levels: [2, 3, 4] },
            link: { openOnClick: false, autolink: false },
        }),
        TextAlign.configure({ types: ['heading', 'paragraph'] }),
        TextStyle,
        Color,
        BackgroundColor,
        YoutubeVideo.configure({ nocookie: true, controls: true }),
        Iframe,
        RichImage.configure({ inline: false, allowBase64: false }),
        Table.configure({ resizable: false }),
        TableRow,
        TableHeader,
        TableCell,
    ];
}
