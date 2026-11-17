/**
 * A file size in bytes as `KB` or `MB` (at least 1 KB), the same look as the media library.
 *
 * @example
 * formatSize(2_621_440); // '2.5 MB'
 * formatSize(300); // '1 KB'
 */
export function formatSize(bytes: number): string {
    return bytes >= 1024 * 1024 ? `${(bytes / (1024 * 1024)).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

/**
 * A date and time in the interface language (`locale` is the code from `useTranslation()`); an empty value gives an empty string.
 *
 * @example
 * formatDateTime('2026-06-15T12:00:00+00:00', 'en'); // 'Jun 15, 2026, 12:00 PM' (in the browser time zone)
 */
export function formatDateTime(value: string | null, locale: string): string {
    return value ? new Intl.DateTimeFormat(locale, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : '';
}
