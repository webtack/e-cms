import { usePage } from '@inertiajs/react';
import { useEditorState, type Editor } from '@tiptap/react';
import {
    AlignCenter,
    AlignJustify,
    AlignLeft,
    AlignRight,
    Baseline,
    Bold,
    Code,
    Frame,
    Heading2,
    Heading3,
    Heading4,
    Highlighter,
    Image as ImageIcon,
    Italic,
    Link2,
    List,
    ListOrdered,
    Loader2,
    Maximize,
    Minimize,
    Pilcrow,
    RemoveFormatting,
    Strikethrough,
    Table2,
    Type,
    Underline,
    Video,
} from 'lucide-react';
import { useEffect, useRef, useState, type ReactNode } from 'react';

import { uploadFile } from '@/Components/Admin/FileUpload/FileUpload';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuRadioGroup, DropdownMenuRadioItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/Components/ui/dropdown-menu';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover';

import { EmojiPicker } from './EmojiPicker';
import { IMAGE_DEFAULT_WIDTH, IMAGE_MAX_WIDTH } from './richTextExtensions';
import { normalizeHexColor, parseIframeInput, parseYoutubeId } from './richTextUtils';

type Translate = (key: string, replace?: Record<string, string | number>) => string;

type Props = {
    editor: Editor;
    t: Translate;
    /** Whether the editor fills the window. */
    fullscreen: boolean;
    onToggleFullscreen: () => void;
    /** Key of the content type and of the field: where an inserted image is uploaded to (see `ImageButton`). */
    contentType: string;
    fieldKey: string;
    /** Called with the reason an image could not be inserted, and with `null` when a new attempt starts or succeeds. */
    onImageError: (message: string | null) => void;
    /** Opens the options dialog of the selected image (see `ImageOptionsDialog`). */
    onOpenImageOptions: () => void;
    /** Whether the raw HTML is shown instead of the formatted text; the formatting actions are off meanwhile. */
    source: boolean;
    onToggleSource: () => void;
};

/** Ready-made colors of the palettes (`#rrggbb`). */
const PALETTE = ['#000000', '#6b7280', '#dc2626', '#ea580c', '#ca8a04', '#16a34a', '#0891b2', '#2563eb', '#7c3aed', '#db2777', '#ffffff', '#fef08a'];

/**
 * The toolbar of the editor: one button per format, pressed while the format is active at the cursor, then the
 * color, embed and view actions.
 */
export function RichTextToolbar({ editor, t, fullscreen, onToggleFullscreen, contentType, fieldKey, onImageError, onOpenImageOptions, source, onToggleSource }: Props) {
    const active = useEditorState({
        editor,
        selector: ({ editor: current }) => ({
            // The block style at the cursor; `null` when it is neither a paragraph nor an h2-h4 (e.g. an image is selected).
            blockStyle: (current.isActive('heading', { level: 2 })
                ? 'h2'
                : current.isActive('heading', { level: 3 })
                  ? 'h3'
                  : current.isActive('heading', { level: 4 })
                    ? 'h4'
                    : current.isActive('paragraph')
                      ? 'p'
                      : null) as BlockStyle | null,
            bold: current.isActive('bold'),
            italic: current.isActive('italic'),
            underline: current.isActive('underline'),
            strike: current.isActive('strike'),
            // The alignment of the block at the cursor; a block without one is aligned left.
            // With an image selected it is the alignment of the image.
            align: current.isActive('image')
                ? ((current.getAttributes('image').align as Alignment | undefined) ?? 'left')
                : (['center', 'right', 'justify'] as const).find((value) => current.isActive({ textAlign: value })) ?? ('left' as const),
            bulletList: current.isActive('bulletList'),
            orderedList: current.isActive('orderedList'),
            link: current.isActive('link'),
            // An image is selected as a node, so its options can be edited.
            image: current.isActive('image'),
            // The cursor is in a table cell: the "Table" button then opens the table actions.
            table: current.isActive('table'),
        }),
    });

    // Bold, italic, underline, links and headings stay; the rest of the formatting goes.
    const clearFormatting = () => editor.chain().focus().unsetStrike().unsetColor().unsetBackgroundColor().unsetTextAlign().run();

    return (
        <div role="toolbar" aria-label={t('Text formatting')} className="flex flex-wrap items-center gap-1 border-b border-input p-1">
            <ToolButton label={t('Source')} pressed={source} onClick={onToggleSource}>
                <Code aria-hidden />
            </ToolButton>
            {/* A disabled fieldset switches off every button inside it; `contents` keeps the toolbar layout. */}
            <fieldset disabled={source} className="contents">
            <BlockStyleMenu editor={editor} t={t} value={active.blockStyle} />
            <ToolButton label={t('Bold')} pressed={active.bold} onClick={() => editor.chain().focus().toggleBold().run()}>
                <Bold aria-hidden />
            </ToolButton>
            <ToolButton label={t('Italic')} pressed={active.italic} onClick={() => editor.chain().focus().toggleItalic().run()}>
                <Italic aria-hidden />
            </ToolButton>
            <ToolButton label={t('Underline')} pressed={active.underline} onClick={() => editor.chain().focus().toggleUnderline().run()}>
                <Underline aria-hidden />
            </ToolButton>
            <ToolButton label={t('Strikethrough')} pressed={active.strike} onClick={() => editor.chain().focus().toggleStrike().run()}>
                <Strikethrough aria-hidden />
            </ToolButton>
            <AlignMenu editor={editor} t={t} value={active.align} image={active.image} />
            <ColorButton editor={editor} t={t} kind="color" />
            <ColorButton editor={editor} t={t} kind="background" />
            <ToolButton label={t('Bulleted list')} pressed={active.bulletList} onClick={() => editor.chain().focus().toggleBulletList().run()}>
                <List aria-hidden />
            </ToolButton>
            <ToolButton label={t('Numbered list')} pressed={active.orderedList} onClick={() => editor.chain().focus().toggleOrderedList().run()}>
                <ListOrdered aria-hidden />
            </ToolButton>
            <LinkButton editor={editor} pressed={active.link} t={t} />
            <ImageButton t={t} editor={editor} selected={active.image} onOpenOptions={onOpenImageOptions} contentType={contentType} fieldKey={fieldKey} onError={onImageError} />
            <TableButton editor={editor} t={t} inTable={active.table} />
            <YoutubeButton editor={editor} t={t} />
            <FrameButton editor={editor} t={t} />
            <EmojiPicker editor={editor} t={t} />
            <ToolButton label={t('Clear formatting')} onClick={clearFormatting}>
                <RemoveFormatting aria-hidden />
            </ToolButton>
            </fieldset>
            <span className="ms-auto flex items-center gap-1">
                <ToolButton label={t('Fullscreen')} pressed={fullscreen} onClick={onToggleFullscreen}>
                    {fullscreen ? <Minimize aria-hidden /> : <Maximize aria-hidden />}
                </ToolButton>
            </span>
        </div>
    );
}

/** A toolbar button; `pressed` is given only for the buttons that have a state (`aria-pressed`). */
function ToolButton({ label, pressed, onClick, children }: { label: string; pressed?: boolean; onClick: () => void; children: ReactNode }) {
    return (
        // Keeping the mouse press from moving the focus leaves the selection and the focus in the editor.
        <Button type="button" variant={pressed ? 'secondary' : 'ghost'} size="icon-sm" aria-label={label} aria-pressed={pressed} title={label} onMouseDown={(e) => e.preventDefault()} onClick={onClick}>
            {children}
        </Button>
    );
}

type BlockStyle = 'p' | 'h2' | 'h3' | 'h4';

/**
 * The block style dropdown: "Normal" (a paragraph) and the headings h2-h4. The button shows the icon of the style at
 * the cursor (a neutral icon when `value` is `null`, e.g. with an image selected) and the menu marks the current one.
 * Choosing sets the style (it never toggles, so choosing the current level changes nothing) and keeps the focus in
 * the editor.
 *
 * @example
 * <BlockStyleMenu editor={editor} t={t} value="h3" />
 */
function BlockStyleMenu({ editor, t, value }: { editor: Editor; t: Translate; value: BlockStyle | null }) {
    const options: { value: BlockStyle; label: string; icon: ReactNode }[] = [
        { value: 'p', label: t('Normal'), icon: <Pilcrow aria-hidden /> },
        { value: 'h2', label: t('Heading 2'), icon: <Heading2 aria-hidden /> },
        { value: 'h3', label: t('Heading 3'), icon: <Heading3 aria-hidden /> },
        { value: 'h4', label: t('Heading 4'), icon: <Heading4 aria-hidden /> },
    ];
    const current = options.find((option) => option.value === value);

    const choose = (next: string) => {
        const chain = editor.chain().focus();
        if (next === 'p') {
            chain.setParagraph().run();
        } else {
            chain.setHeading({ level: Number(next.slice(1)) as 2 | 3 | 4 }).run();
        }
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button type="button" variant="ghost" size="icon-sm" aria-label={t('Block style')} title={current ? `${t('Block style')}: ${current.label}` : t('Block style')} onMouseDown={(e) => e.preventDefault()}>
                    {current ? current.icon : <Type aria-hidden />}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" className="w-max min-w-48" onCloseAutoFocus={(e) => e.preventDefault()}>
                <DropdownMenuRadioGroup value={value ?? ''} onValueChange={choose}>
                    {options.map((option) => (
                        <DropdownMenuRadioItem key={option.value} value={option.value} className="whitespace-nowrap data-[state=checked]:bg-accent">
                            {option.icon}
                            {option.label}
                        </DropdownMenuRadioItem>
                    ))}
                </DropdownMenuRadioGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

type Alignment = 'left' | 'center' | 'right' | 'justify';

/**
 * The alignment dropdown: the button shows the alignment of the block at the cursor (left when it has none) and the
 * menu lists the four alignments with the current one marked. With an image selected it aligns the image (and its
 * caption) instead: left, center or right; "Justify" is off.
 */
function AlignMenu({ editor, t, value, image }: { editor: Editor; t: Translate; value: Alignment; image: boolean }) {
    const options: { value: Alignment; label: string; icon: ReactNode }[] = [
        { value: 'left', label: t('Align left'), icon: <AlignLeft aria-hidden /> },
        { value: 'center', label: t('Align center'), icon: <AlignCenter aria-hidden /> },
        { value: 'right', label: t('Align right'), icon: <AlignRight aria-hidden /> },
        { value: 'justify', label: t('Justify'), icon: <AlignJustify aria-hidden /> },
    ];
    const current = options.find((option) => option.value === value) ?? options[0];

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button type="button" variant="ghost" size="icon-sm" aria-label={t('Text alignment')} title={t('Text alignment')} onMouseDown={(e) => e.preventDefault()}>
                    {current.icon}
                </Button>
            </DropdownMenuTrigger>
            {/* By default the menu is as wide as its trigger; it must fit the longest label (any language) on one line. */}
            <DropdownMenuContent align="start" className="w-max min-w-48" onCloseAutoFocus={(e) => e.preventDefault()}>
                <DropdownMenuRadioGroup value={value} onValueChange={(next) => (image ? editor.chain().focus().updateAttributes('image', { align: next }).run() : editor.chain().focus().setTextAlign(next as Alignment).run())}>
                    {options.map((option) => (
                        <DropdownMenuRadioItem key={option.value} value={option.value} disabled={image && option.value === 'justify'} className="whitespace-nowrap data-[state=checked]:bg-accent">
                            {option.icon}
                            {option.label}
                        </DropdownMenuRadioItem>
                    ))}
                </DropdownMenuRadioGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

/**
 * The "Table" button. Outside a table it inserts a table of three rows and three columns with a header row (the
 * cursor lands in the first cell); inside a table (`inTable`, shown as pressed) it opens a menu of the table actions
 * on the row and the column at the cursor, each disabled while the command cannot run there.
 *
 * @example
 * <TableButton editor={editor} t={t} inTable={editor.isActive('table')} />
 */
function TableButton({ editor, t, inTable }: { editor: Editor; t: Translate; inTable: boolean }) {
    const label = t('Table');

    if (!inTable) {
        return (
            <ToolButton label={label} pressed={false} onClick={() => editor.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run()}>
                <Table2 aria-hidden />
            </ToolButton>
        );
    }

    // `can()` is read on render; the toolbar re-renders when the selection moves, so the states follow the cursor.
    const actions: { label: string; can: boolean; run: () => void; separated?: boolean }[] = [
        { label: t('Add row above'), can: editor.can().addRowBefore(), run: () => editor.chain().focus().addRowBefore().run() },
        { label: t('Add row below'), can: editor.can().addRowAfter(), run: () => editor.chain().focus().addRowAfter().run() },
        { label: t('Delete row'), can: editor.can().deleteRow(), run: () => editor.chain().focus().deleteRow().run() },
        { label: t('Add column before'), can: editor.can().addColumnBefore(), run: () => editor.chain().focus().addColumnBefore().run(), separated: true },
        { label: t('Add column after'), can: editor.can().addColumnAfter(), run: () => editor.chain().focus().addColumnAfter().run() },
        { label: t('Delete column'), can: editor.can().deleteColumn(), run: () => editor.chain().focus().deleteColumn().run() },
        { label: t('Switch header row'), can: editor.can().toggleHeaderRow(), run: () => editor.chain().focus().toggleHeaderRow().run(), separated: true },
        { label: t('Delete table'), can: editor.can().deleteTable(), run: () => editor.chain().focus().deleteTable().run(), separated: true },
    ];

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button type="button" variant="secondary" size="icon-sm" aria-label={label} aria-pressed title={label} onMouseDown={(e) => e.preventDefault()}>
                    <Table2 aria-hidden />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" className="w-max min-w-48" onCloseAutoFocus={(e) => e.preventDefault()}>
                {actions.map((action) => (
                    <div key={action.label}>
                        {action.separated && <DropdownMenuSeparator />}
                        <DropdownMenuItem disabled={!action.can} className="whitespace-nowrap" onSelect={action.run}>
                            {action.label}
                        </DropdownMenuItem>
                    </div>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

/**
 * Insert a block node (a frame, a video) after the selection. `insertContent` would replace a selected node, which is
 * what the cursor is on right after an earlier insert, so a second embed would swallow the first one.
 */
function insertBlockAfterSelection(editor: Editor, content: { type: string; attrs: Record<string, unknown> }) {
    editor.chain().focus().insertContentAt(editor.state.selection.to, content).run();
}

/**
 * The text color or background color button: a popover with a palette, a `#rrggbb` field and "Reset color".
 * A color is stored as `#rrggbb` (see `normalizeHexColor`).
 */
function ColorButton({ editor, t, kind }: { editor: Editor; t: Translate; kind: 'color' | 'background' }) {
    const [open, setOpen] = useState(false);
    const [hex, setHex] = useState('');
    const [invalid, setInvalid] = useState(false);
    const label = kind === 'color' ? t('Text color') : t('Background color');

    const set = (color: string) => {
        const chain = editor.chain().focus();
        (kind === 'color' ? chain.setColor(color) : chain.setBackgroundColor(color)).run();
        setOpen(false);
    };

    const applyTyped = () => {
        const color = normalizeHexColor(hex);
        setInvalid(color === null);
        if (color) {
            set(color);
        }
    };

    const reset = () => {
        const chain = editor.chain().focus();
        (kind === 'color' ? chain.unsetColor() : chain.unsetBackgroundColor()).run();
        setOpen(false);
    };

    return (
        <Popover
            open={open}
            onOpenChange={(next) => {
                if (next) {
                    setHex('');
                    setInvalid(false);
                }
                setOpen(next);
            }}
        >
            <PopoverTrigger asChild>
                <Button type="button" variant="ghost" size="icon-sm" aria-label={label} title={label}>
                    {kind === 'color' ? <Baseline aria-hidden /> : <Highlighter aria-hidden />}
                </Button>
            </PopoverTrigger>
            <PopoverContent align="start" className="gap-2" onCloseAutoFocus={(e) => e.preventDefault()}>
                <div role="group" aria-label={label} className="grid grid-cols-6 gap-1">
                    {PALETTE.map((color) => (
                        <button
                            key={color}
                            type="button"
                            aria-label={color}
                            title={color}
                            className="size-6 rounded-sm border border-input"
                            style={{ backgroundColor: color }}
                            onMouseDown={(e) => e.preventDefault()}
                            onClick={() => set(color)}
                        />
                    ))}
                </div>
                <Input
                    value={hex}
                    placeholder="#rrggbb"
                    aria-label={t('Color code')}
                    aria-invalid={invalid || undefined}
                    onChange={(e) => setHex(e.target.value)}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            applyTyped();
                        }
                    }}
                />
                {invalid && <p className="text-sm text-destructive">{t('Enter a color as #rrggbb.')}</p>}
                <div className="flex justify-end gap-2">
                    <Button type="button" variant="outline" size="sm" onClick={reset}>
                        {t('Reset color')}
                    </Button>
                    <Button type="button" size="sm" onClick={applyTyped}>
                        {t('Apply')}
                    </Button>
                </div>
            </PopoverContent>
        </Popover>
    );
}

/**
 * The link button: a popover with the address field. "Apply" sets the link on the selection (an empty address
 * removes it), "Remove link" unsets an existing one.
 */
function LinkButton({ editor, pressed, t }: { editor: Editor; pressed: boolean; t: Translate }) {
    const [open, setOpen] = useState(false);
    const [href, setHref] = useState('');

    const change = (next: boolean) => {
        if (next) {
            setHref((editor.getAttributes('link').href as string | undefined) ?? '');
        }
        setOpen(next);
    };

    const apply = () => {
        const address = href.trim();
        const chain = editor.chain().focus().extendMarkRange('link');
        (address === '' ? chain.unsetLink() : chain.setLink({ href: address })).run();
        setOpen(false);
    };

    return (
        <Popover open={open} onOpenChange={change}>
            <PopoverTrigger asChild>
                <Button type="button" variant={pressed ? 'secondary' : 'ghost'} size="icon-sm" aria-label={t('Link')} aria-pressed={pressed} title={t('Link')}>
                    <Link2 aria-hidden />
                </Button>
            </PopoverTrigger>
            <PopoverContent align="start" className="gap-2">
                <Input
                    value={href}
                    placeholder="https://"
                    aria-label={t('Link address')}
                    onChange={(e) => setHref(e.target.value)}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            apply();
                        }
                    }}
                />
                <div className="flex justify-end gap-2">
                    {pressed && (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => {
                                editor.chain().focus().extendMarkRange('link').unsetLink().run();
                                setOpen(false);
                            }}
                        >
                            {t('Remove link')}
                        </Button>
                    )}
                    <Button type="button" size="sm" onClick={apply}>
                        {t('Apply')}
                    </Button>
                </div>
            </PopoverContent>
        </Popover>
    );
}

/**
 * A popover with one address field, an error under it and an "Insert" button. `insert` returns an error
 * message to show, or `null` when it inserted the content (the popover then closes).
 */
function InsertPopover({ icon, label, hint, placeholder, multiline, insert, t }: { icon: ReactNode; label: string; hint: string; placeholder: string; multiline?: boolean; insert: (value: string) => string | null; t: Translate }) {
    const [open, setOpen] = useState(false);
    const [value, setValue] = useState('');
    const [error, setError] = useState<string | null>(null);

    const submit = () => {
        const problem = insert(value.trim());
        setError(problem);
        if (problem === null) {
            setOpen(false);
        }
    };

    return (
        <Popover
            open={open}
            onOpenChange={(next) => {
                if (next) {
                    setValue('');
                    setError(null);
                }
                setOpen(next);
            }}
        >
            <PopoverTrigger asChild>
                <Button type="button" variant="ghost" size="icon-sm" aria-label={label} title={label}>
                    {icon}
                </Button>
            </PopoverTrigger>
            <PopoverContent align="start" className="gap-2">
                <p className="text-muted-foreground">{hint}</p>
                {multiline ? (
                    <textarea
                        value={value}
                        rows={3}
                        placeholder={placeholder}
                        aria-label={label}
                        aria-invalid={error ? 'true' : undefined}
                        className="rounded-md border border-input bg-transparent px-3 py-2 text-base outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 md:text-sm"
                        onChange={(e) => setValue(e.target.value)}
                    />
                ) : (
                    <Input
                        value={value}
                        placeholder={placeholder}
                        aria-label={label}
                        aria-invalid={error ? 'true' : undefined}
                        onChange={(e) => setValue(e.target.value)}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') {
                                e.preventDefault();
                                submit();
                            }
                        }}
                    />
                )}
                {error && <p className="text-sm text-destructive">{error}</p>}
                <div className="flex justify-end">
                    <Button type="button" size="sm" onClick={submit}>
                        {t('Insert')}
                    </Button>
                </div>
            </PopoverContent>
        </Popover>
    );
}

/**
 * The image button. With an image selected it opens the options of that image (`onOpenOptions`); otherwise it picks a
 * file and uploads it at once for the field (`POST /admin/files` with `content_type` and
 * `field_key`, so the rules are those of the `image` profile), then inserts it at the cursor with the relative
 * address of the stored file (the path of the answer's `url`, so no protocol or host is kept in the text) and the
 * file name as `alt`. The button is off while uploading; a failure goes to `onError` and leaves the text untouched.
 *
 * @example
 * <ImageButton editor={editor} t={t} selected={false} onOpenOptions={open} contentType="news" fieldKey="body" onError={setError} />
 */
function ImageButton({ editor, t, selected, onOpenOptions, contentType, fieldKey, onError }: { editor: Editor; t: Translate; selected: boolean; onOpenOptions: () => void; contentType: string; fieldKey: string; onError: (message: string | null) => void }) {
    const input = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);

    const upload = async (file: File) => {
        setUploading(true);
        onError(null);

        try {
            const stored = await uploadFile(file, { field: { contentType, fieldKey } });
            const src = new URL(stored.url, window.location.origin).pathname;
            insertBlockAfterSelection(editor, { type: 'image', attrs: { src, alt: stored.name, width: IMAGE_DEFAULT_WIDTH } });
        } catch (failure) {
            onError((failure as Error).message || t('The file could not be uploaded.'));
        } finally {
            setUploading(false);
        }
    };

    return (
        <>
            <Button type="button" variant="ghost" size="icon-sm" aria-label={t('Image')} title={t('Image')} disabled={uploading} onMouseDown={(e) => e.preventDefault()} onClick={() => (selected ? onOpenOptions() : input.current?.click())}>
                {uploading ? <Loader2 className="animate-spin" aria-hidden /> : <ImageIcon aria-hidden />}
            </Button>
            <input
                ref={input}
                type="file"
                accept="image/*"
                tabIndex={-1}
                aria-hidden
                data-testid="rich-text-image-input"
                className="hidden"
                onChange={(e) => {
                    const file = e.target.files?.[0];
                    // The same file may be picked again after a failure.
                    e.target.value = '';
                    if (file) {
                        void upload(file);
                    }
                }}
            />
        </>
    );
}

/**
 * The "Image options" dialog of the selected image: the width in pixels (a whole number from 1 to `IMAGE_MAX_WIDTH`;
 * empty or "Original size" keeps the natural size), the alt text and the caption (empty removes it). "Done" applies the
 * three values; an invalid width is reported at the field and nothing is applied. It opens on a double click on the
 * image and from the image button while an image is selected.
 *
 * @example
 * <ImageOptionsDialog editor={editor} t={t} open={open} onOpenChange={setOpen} />
 */
export function ImageOptionsDialog({ editor, t, open, onOpenChange }: { editor: Editor; t: Translate; open: boolean; onOpenChange: (open: boolean) => void }) {
    const [width, setWidth] = useState('');
    const [alt, setAlt] = useState('');
    const [caption, setCaption] = useState('');
    const [error, setError] = useState<string | null>(null);

    // Fill the fields with the values of the selected image each time the dialog opens.
    useEffect(() => {
        if (open) {
            const attrs = editor.getAttributes('image');
            setWidth(attrs.width ? String(attrs.width) : '');
            setAlt((attrs.alt as string | null) ?? '');
            setCaption((attrs.caption as string | null) ?? '');
            setError(null);
        }
    }, [open, editor]);

    const apply = () => {
        const text = width.trim();
        const pixels = text === '' ? null : Number(text);

        if (pixels !== null && (!/^\d+$/.test(text) || pixels < 1 || pixels > IMAGE_MAX_WIDTH)) {
            setError(t('Enter a whole number of pixels from 1 to :max.', { max: IMAGE_MAX_WIDTH }));
            return;
        }

        editor.chain().focus().updateAttributes('image', { width: pixels, alt: alt.trim() || null, caption: caption.trim() || null }).run();
        onOpenChange(false);
    };

    const original = () => {
        editor.chain().focus().updateAttributes('image', { width: null }).run();
        onOpenChange(false);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                onCloseAutoFocus={(e) => {
                    e.preventDefault();
                    editor.commands.focus();
                }}
            >
                <DialogHeader>
                    <DialogTitle>{t('Image options')}</DialogTitle>
                    <DialogDescription className="sr-only">{t('Width, alternative text and caption of the image.')}</DialogDescription>
                </DialogHeader>
                <label className="grid gap-1 text-sm">
                    {t('Width (px)')}
                    <Input value={width} inputMode="numeric" aria-invalid={error ? 'true' : undefined} onChange={(e) => setWidth(e.target.value)} />
                </label>
                {error && <p className="text-sm text-destructive">{error}</p>}
                <label className="grid gap-1 text-sm">
                    {t('Alternative text')}
                    <Input value={alt} onChange={(e) => setAlt(e.target.value)} />
                </label>
                <label className="grid gap-1 text-sm">
                    {t('Caption')}
                    <Input value={caption} onChange={(e) => setCaption(e.target.value)} />
                </label>
                <DialogFooter>
                    <Button type="button" variant="outline" size="sm" onClick={original}>
                        {t('Original size')}
                    </Button>
                    <Button type="button" size="sm" onClick={apply}>
                        {t('Done')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

/** Insert a YouTube video by address (`youtu.be/ID`, `watch?v=ID`, `embed/ID`, `shorts/ID`). */
function YoutubeButton({ editor, t }: { editor: Editor; t: Translate }) {
    return (
        <InsertPopover
            t={t}
            icon={<Video aria-hidden />}
            label={t('YouTube video')}
            hint={t('Paste the address of a YouTube video.')}
            placeholder="https://youtu.be/…"
            insert={(address) => {
                const id = parseYoutubeId(address);
                if (!id) {
                    return t('This is not a YouTube video address.');
                }
                insertBlockAfterSelection(editor, { type: 'youtube', attrs: { src: `https://www.youtube.com/watch?v=${id}` } });
                return null;
            }}
        />
    );
}

/** Insert a frame by an `https` address or by `<iframe>` code; only hosts of the shared allow-list are accepted. */
function FrameButton({ editor, t }: { editor: Editor; t: Translate }) {
    const { iframeHosts } = usePage().props.richtext;

    return (
        <InsertPopover
            t={t}
            multiline
            icon={<Frame aria-hidden />}
            label={t('Frame')}
            hint={t('Paste an https address or the embed code (<iframe>) of Google Drive, Google Forms, a map, a video or a social network post.')}
            placeholder="https://… / <iframe …></iframe>"
            insert={(input) => {
                const frame = parseIframeInput(input, iframeHosts);
                if (!frame) {
                    return t('This address is not allowed in a frame.');
                }
                const id = parseYoutubeId(frame.src);
                insertBlockAfterSelection(editor, id ? { type: 'youtube', attrs: { src: `https://www.youtube.com/watch?v=${id}` } } : { type: 'iframe', attrs: frame });
                return null;
            }}
        />
    );
}
