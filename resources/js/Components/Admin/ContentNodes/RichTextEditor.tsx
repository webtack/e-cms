import { DOMParser as ProseDOMParser } from '@tiptap/pm/model';
import { GapCursor } from '@tiptap/pm/gapcursor';
import { NodeSelection } from '@tiptap/pm/state';
import type { Editor } from '@tiptap/react';
import { EditorContent, useEditor } from '@tiptap/react';
import { useEffect, useState } from 'react';

import { useTranslation } from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';

import { richTextExtensions } from './richTextExtensions';
import { ImageOptionsDialog, RichTextToolbar } from './RichTextToolbar';
import { convertPastedText, convertWordLists } from './richTextUtils';

/**
 * Take the selection off an image the document selected by itself: ProseMirror starts on the first atom node, so a
 * text that begins with an image would open with that image selected. The cursor goes before it as a gap cursor; only
 * the user selects an image (a click, a double click, an insert).
 *
 * @example
 * editor.commands.setContent('<img src="/storage/0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.png">');
 * deselectImage(editor);   // editor.isActive('image') === false
 */
function deselectImage(editor: Editor) {
    const { selection, doc } = editor.state;

    if (selection instanceof NodeSelection && selection.node.type.name === 'image') {
        editor.view.dispatch(editor.state.tr.setSelection(new GapCursor(doc.resolve(selection.from))));
    }
}

type Props = {
    /** The HTML of the value; `''` or `null` for an empty editor. */
    value: string;
    /** Called with the HTML after each edit, and with `''` when the document has no content. */
    onChange: (value: string) => void;
    /** The DOM id of the editable element; the field's `<label htmlFor>` points at it. */
    id: string;
    /** Marks the editable element and the frame as invalid. */
    invalid?: boolean;
    /** Accessible name; needed when no `<label>` points at the element (items of a multiple field). */
    'aria-label'?: string;
    /** Key of the content type the field belongs to; with `fieldKey` it tells the upload endpoint where an image is inserted. */
    contentType: string;
    /** Key of the field; images are uploaded under the rules of its `richtext` type (the `image` profile). */
    fieldKey: string;
};

/**
 * A rich text editor (Tiptap): headings, bold, italic, underline, strike-through, alignment, text and background
 * color, bulleted and numbered lists, links, images (uploaded to the media library, with width, alt text and caption),
 * YouTube videos, frames of allowed hosts, emoji, clearing the formatting, and a fullscreen mode and a raw HTML view
 * ("Source"). There are no actions for other files. The value is an HTML string; the server sanitises it against an
 * allow-list, so the editor only has to produce markup that list allows. An empty document is reported as `''`.
 *
 * The editor is the source of truth while typing: an incoming `value` replaces the content only when it differs
 * from what the editor already holds, so the cursor does not jump on every keystroke.
 *
 * @example
 * <RichTextEditor id="field-body" contentType="news" fieldKey="body" value={data.values.body} onChange={(html) => setValue('body', html)} />
 */
export function RichTextEditor({ value, onChange, id, invalid, 'aria-label': ariaLabel, contentType, fieldKey }: Props) {
    const { t } = useTranslation();
    const [fullscreen, setFullscreen] = useState(false);
    const [source, setSource] = useState(false);
    const [draft, setDraft] = useState('');
    const [imageError, setImageError] = useState<string | null>(null);
    const [imageOptionsOpen, setImageOptionsOpen] = useState(false);
    const editor = useEditor({
        // Only what the server allow-list keeps.
        extensions: richTextExtensions(),
        content: value || '',
        immediatelyRender: false,
        editorProps: {
            // A double click on an image selects it and opens its options.
            handleDoubleClickOn: (view, _pos, node, nodePos) => {
                if (node.type.name !== 'image') {
                    return false;
                }
                view.dispatch(view.state.tr.setSelection(NodeSelection.create(view.state.doc, nodePos)));
                setImageOptionsOpen(true);

                return true;
            },
            // Lists copied from Word become real lists (see `convertWordLists`); other HTML is pasted as before.
            transformPastedHTML: convertWordLists,
            // Plain text (no HTML on the clipboard) with typed markers (`•    text`, `1.    text`) becomes lists too.
            handlePaste: (view, event) => {
                const data = event.clipboardData;
                if (!data || data.getData('text/html')) {
                    return false;
                }
                const html = convertPastedText(data.getData('text/plain'));
                if (html === null) {
                    return false;
                }
                const dom = new window.DOMParser().parseFromString(html, 'text/html').body;
                view.dispatch(view.state.tr.replaceSelection(ProseDOMParser.fromSchema(view.state.schema).parseSlice(dom)).scrollIntoView());

                return true;
            },
            attributes: {
                id,
                role: 'textbox',
                'aria-multiline': 'true',
                // A `div` is not labelable, so the field's label (`{id}-label`) names it through `aria-labelledby`.
                ...(ariaLabel ? { 'aria-label': ariaLabel } : { 'aria-labelledby': `${id}-label` }),
                ...(invalid ? { 'aria-invalid': 'true' } : {}),
                class: 'rich-text-content min-h-32 px-3 py-2 text-base outline-none md:text-sm',
            },
        },
        onCreate: ({ editor: created }) => deselectImage(created),
        onUpdate: ({ editor: current }) => onChange(current.isEmpty ? '' : current.getHTML()),
    });

    // Follow an outside change of the value (a reset, a server round trip). While the editor has the focus the user is
    // typing and the echo of an earlier keystroke may arrive late; applying it would reset the text and the cursor.
    useEffect(() => {
        if (editor && !editor.isFocused && value !== (editor.isEmpty ? '' : editor.getHTML())) {
            editor.commands.setContent(value || '', { emitUpdate: false });
            deselectImage(editor);
        }
    }, [editor, value]);

    // The editable element carries the invalid mark, so keep it in step with the prop after the first render.
    useEffect(() => {
        if (editor) {
            editor.setOptions({ editorProps: { attributes: { ...editor.options.editorProps.attributes, ...(invalid ? { 'aria-invalid': 'true' } : { 'aria-invalid': 'false' }) } as Record<string, string> } });
        }
    }, [editor, invalid]);

    // Fullscreen: Escape leaves it and the page behind does not scroll meanwhile.
    useEffect(() => {
        if (!fullscreen) {
            return;
        }
        const leave = (e: KeyboardEvent) => e.key === 'Escape' && setFullscreen(false);
        const overflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        document.addEventListener('keydown', leave);

        return () => {
            document.body.style.overflow = overflow;
            document.removeEventListener('keydown', leave);
        };
    }, [fullscreen]);

    return (
        <div
            data-slot="rich-text-editor"
            aria-invalid={invalid || undefined}
            className={cn(
                'rounded-md border border-input bg-transparent shadow-xs transition-[color,box-shadow] focus-within:border-ring focus-within:ring-3 focus-within:ring-ring/50 dark:bg-input/30',
                fullscreen && 'fixed inset-0 z-40 flex flex-col rounded-none bg-background dark:bg-background',
                invalid && 'border-destructive ring-3 ring-destructive/20 dark:border-destructive/50 dark:ring-destructive/40',
            )}
        >
            {editor && <RichTextToolbar
                    editor={editor}
                    t={t}
                    fullscreen={fullscreen}
                    onToggleFullscreen={() => setFullscreen((on) => !on)}
                    contentType={contentType}
                    fieldKey={fieldKey}
                    onImageError={setImageError}
                    onOpenImageOptions={() => setImageOptionsOpen(true)}
                    source={source}
                    onToggleSource={() => {
                        if (!source) {
                            setDraft(formatSource(editor.isEmpty ? '' : editor.getHTML()));
                        }
                        setSource((on) => !on);
                    }}
                />}
            {editor && <ImageOptionsDialog editor={editor} t={t} open={imageOptionsOpen} onOpenChange={setImageOptionsOpen} />}
            {imageError && (
                <p role="alert" className="border-b border-input px-3 py-1 text-sm text-destructive">
                    {imageError}
                </p>
            )}
            {/* The editor stays mounted while the source is shown, so its content and history are kept. */}
            <EditorContent editor={editor} className={cn(fullscreen && 'min-h-0 flex-1 overflow-auto', source && 'hidden')} />
            {source && (
                <textarea
                    value={draft}
                    spellCheck={false}
                    aria-label={t('HTML source')}
                    className={cn('block min-h-32 w-full resize-y bg-transparent px-3 py-2 font-mono text-sm outline-none', fullscreen && 'min-h-0 flex-1')}
                    onChange={(e) => {
                        setDraft(e.target.value);
                        // Applying it at once also reports it through `onUpdate`; the server sanitises the result.
                        editor?.commands.setContent(e.target.value, { emitUpdate: true });
                    }}
                />
            )}
        </div>
    );
}

/**
 * Put each block of the editor's HTML on its own line so the source is readable.
 *
 * @example
 * formatSource('<p>a</p><p>b</p>');   // '<p>a</p>\n<p>b</p>'
 */
function formatSource(html: string): string {
    return html.replace(/<\/(p|h[2-4]|ul|ol|li|blockquote)>(?!\n|$)/g, '</$1>\n');
}
