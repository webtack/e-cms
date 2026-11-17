import type { Editor } from '@tiptap/react';
import { Smile } from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/Components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover';

/** Popular emoji in groups; picking one inserts the character, nothing is stored but text. */
const EMOJI_GROUPS: { label: string; emoji: string[] }[] = [
    { label: 'Faces', emoji: ['😀', '😃', '😄', '😁', '😆', '😅', '😂', '🙂', '😉', '😊', '😍', '😘', '😎', '🤔', '😐', '😢', '😭', '😡', '🥳', '😴'] },
    { label: 'Gestures', emoji: ['👍', '👎', '👏', '🙌', '🙏', '💪', '👌', '✌️', '🤝', '👋', '☝️', '👀'] },
    { label: 'Symbols', emoji: ['❤️', '🔥', '⭐', '✨', '✅', '❌', '⚠️', '❓', '❗', '💡', '🎉', '🎁', '📌', '📎', '📅', '📧', '📞', '🔗', '🏠', '🚀'] },
];

/**
 * The emoji button: a popover with a grid of emoji. Picking one inserts the character at the cursor and keeps the
 * focus in the editor.
 *
 * @example
 * <EmojiPicker editor={editor} t={t} />
 */
export function EmojiPicker({ editor, t }: { editor: Editor; t: (key: string) => string }) {
    const [open, setOpen] = useState(false);

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button type="button" variant="ghost" size="icon-sm" aria-label={t('Emoji')} title={t('Emoji')}>
                    <Smile aria-hidden />
                </Button>
            </PopoverTrigger>
            <PopoverContent align="start" className="gap-2" onCloseAutoFocus={(e) => e.preventDefault()}>
                {EMOJI_GROUPS.map((group) => (
                    <div key={group.label} role="group" aria-label={t(group.label)} className="grid grid-cols-10 gap-0.5">
                        {group.emoji.map((emoji) => (
                            <button
                                key={emoji}
                                type="button"
                                aria-label={emoji}
                                className="rounded-sm p-0.5 text-lg leading-none hover:bg-accent"
                                // Keeping the mouse press from moving the focus leaves the cursor in the editor.
                                onMouseDown={(e) => e.preventDefault()}
                                onClick={() => {
                                    editor.chain().focus().insertContent(emoji).run();
                                    setOpen(false);
                                }}
                            >
                                {emoji}
                            </button>
                        ))}
                    </div>
                ))}
            </PopoverContent>
        </Popover>
    );
}
