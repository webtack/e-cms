/** Derived state of a node: what the site does with it. */
export type NodeStatus = 'published' | 'scheduled' | 'expired' | 'disabled';


/**
 * The current moment as `YYYY-MM-DDTHH:mm` in UTC, the format of `published_at` in the form.
 * The same strings order lexicographically, so they can be compared as text.
 *
 * @example
 * formatNow(new Date('2026-10-31T12:34:56Z')); // '2026-10-31T12:34'
 */
export function formatNow(now: Date = new Date()): string {
    return now.toISOString().slice(0, 16);
}

/**
 * The state of a node from the form state (before saving), checked in this order: a disabled node is
 * `disabled` whatever its dates; an active one with a future `publishedAt` is `scheduled`; one whose
 * `unpublishedAt` has been reached is `expired`; otherwise `published`. An empty or invalid `publishedAt`
 * counts as "now" (the server fills it on create), an empty or invalid `unpublishedAt` as "no end date".
 * Mirrors `ContentNode::status()`.
 *
 * @example
 * nodeStatus(false, '2030-01-01T00:00', '', new Date('2026-01-01Z'));                  // 'disabled'
 * nodeStatus(true, '2030-01-01T00:00', '', new Date('2026-01-01Z'));                   // 'scheduled'
 * nodeStatus(true, '2020-01-01T00:00', '2021-01-01T00:00', new Date('2026-01-01Z'));   // 'expired'
 * nodeStatus(true, '2020-01-01T00:00', '', new Date('2026-01-01Z'));                   // 'published'
 */
export function nodeStatus(isActive: boolean, publishedAt: string, unpublishedAt: string = '', now: Date = new Date()): NodeStatus {
    if (!isActive) {
        return 'disabled';
    }

    const valid = (value: string) => /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(value);
    const current = formatNow(now);

    if (valid(publishedAt) && publishedAt > current) {
        return 'scheduled';
    }

    return valid(unpublishedAt) && unpublishedAt <= current ? 'expired' : 'published';
}
