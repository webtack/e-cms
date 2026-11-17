import { cn } from '@/lib/utils';

type Props = {
    /** Full name; the initials come from its first two words. */
    name: string;
    /** URL of the user's picture; without it the initials are shown. */
    src?: string | null;
    /** An active user gets a soft accent tint, an inactive one a neutral grey. Defaults to `true`. */
    active?: boolean;
    className?: string;
};

/** First letters of up to two words of the name, in upper case. */
function initials(name: string): string {
    return name
        .trim()
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((word) => Array.from(word)[0].toUpperCase())
        .join('');
}

/**
 * A round decorative avatar: the user's picture (`src`, cropped to the circle with `object-cover`) or, when there
 * is none, the initials. It is `aria-hidden`: the name must always be rendered next to it, so assistive technology
 * reads the name only. The size comes from `className` (default `size-6`).
 *
 * @example
 * <span className="inline-flex items-center gap-2">
 *     <UserAvatar name="Oksana Melnyk" src={user.avatar} active={user.is_active} />
 *     {user.name}
 * </span>
 */
export function UserAvatar({ name, src, active = true, className }: Props) {
    return (
        <span
            aria-hidden="true"
            className={cn(
                'inline-flex size-6 shrink-0 items-center justify-center overflow-hidden rounded-full text-xs font-medium',
                active ? 'bg-primary/15 text-link' : 'bg-muted text-muted-foreground',
                className,
            )}
        >
            {src ? <img src={src} alt="" className="size-full object-cover" /> : initials(name)}
        </span>
    );
}
