/** An iframe the editor may insert: only what the server allow-list keeps. */
export type IframeAttributes = {
    src: string;
    width: string | null;
    height: string | null;
    title: string | null;
    allowfullscreen: boolean;
};

/**
 * Pull the video id out of a YouTube address: `youtu.be/ID`, `youtube.com/watch?v=ID`, `/embed/ID`, `/shorts/ID`
 * (also `m.` and `www.` hosts and `youtube-nocookie.com`). Returns `null` for anything else.
 *
 * @example
 * parseYoutubeId('https://youtu.be/dQw4w9WgXcQ');                  // 'dQw4w9WgXcQ'
 * parseYoutubeId('https://www.youtube.com/watch?v=dQw4w9WgXcQ');   // 'dQw4w9WgXcQ'
 * parseYoutubeId('https://example.com/watch?v=dQw4w9WgXcQ');       // null
 */
export function parseYoutubeId(input: string): string | null {
    const url = toUrl(input.trim());
    if (!url || url.protocol !== 'https:') {
        return null;
    }

    const host = url.hostname.toLowerCase().replace(/^(www|m)\./, '');
    const valid = (id: string | null | undefined) => (id && /^[\w-]{6,}$/.test(id) ? id : null);

    if (host === 'youtu.be') {
        return valid(url.pathname.split('/')[1]);
    }

    if (host === 'youtube.com' || host === 'youtube-nocookie.com') {
        const [, kind, id] = url.pathname.split('/');
        if (url.pathname === '/watch') {
            return valid(url.searchParams.get('v'));
        }
        if (kind === 'embed' || kind === 'shorts') {
            return valid(id);
        }
    }

    return null;
}

/**
 * Read an iframe from what the user pasted: either an `https` address or an `<iframe …></iframe>` code (the first
 * frame in the text). Only `src`, `width`, `height`, `title` and `allowfullscreen` are carried over. The address
 * must be `https` and match `allowedHosts` by the same rule as the server: the host exactly, and, when the entry
 * has a path prefix, the path starting with it. Returns `null` when nothing usable or allowed was found.
 *
 * @example
 * parseIframeInput('https://drive.google.com/file/d/abc/preview', ['drive.google.com/']);
 * // { src: 'https://drive.google.com/file/d/abc/preview', width: null, height: null, title: null, allowfullscreen: false }
 * parseIframeInput('<iframe src="https://player.vimeo.com/video/1" width="560"></iframe>', ['player.vimeo.com/']);
 * // { src: '…', width: '560', … }
 * parseIframeInput('https://evil.example/x', ['drive.google.com/']);   // null
 */
export function parseIframeInput(input: string, allowedHosts: string[]): IframeAttributes | null {
    const text = input.trim();
    let attributes: IframeAttributes;

    if (/<iframe[\s>]/i.test(text)) {
        const frame = new DOMParser().parseFromString(text, 'text/html').querySelector('iframe');
        if (!frame) {
            return null;
        }
        const size = (name: string) => {
            const value = frame.getAttribute(name)?.trim() ?? '';
            return /^\d+%?$/.test(value) ? value : null;
        };
        attributes = {
            src: (frame.getAttribute('src') ?? '').trim(),
            width: size('width'),
            height: size('height'),
            title: frame.getAttribute('title')?.trim() || null,
            allowfullscreen: frame.hasAttribute('allowfullscreen'),
        };
    } else {
        attributes = { src: text, width: null, height: null, title: null, allowfullscreen: false };
    }

    return isAllowedFrameAddress(attributes.src, allowedHosts) ? attributes : null;
}

/**
 * Whether an address may be framed: `https`, no credentials, host equal to an entry of `allowedHosts`
 * and, when the entry has a path prefix, a path that starts with it. Mirrors `IframeSrcSanitizer` on the server.
 *
 * @example
 * isAllowedFrameAddress('https://www.facebook.com/plugins/post.php', ['www.facebook.com/plugins/']);   // true
 * isAllowedFrameAddress('https://www.facebook.com/some.page', ['www.facebook.com/plugins/']);          // false
 */
export function isAllowedFrameAddress(address: string, allowedHosts: string[]): boolean {
    const url = toUrl(address);
    if (!url || url.protocol !== 'https:' || url.username || url.password || (url.port && url.port !== '443')) {
        return false;
    }

    const path = url.pathname.toLowerCase();

    return allowedHosts.some((entry) => {
        const [host, ...rest] = entry.toLowerCase().split('/');
        return url.hostname.toLowerCase() === host && path.startsWith(`/${rest.join('/')}`);
    });
}

/**
 * Normalise a typed color to lowercase `#rrggbb`. Accepts `#rgb`, `#rrggbb` with or without the `#`.
 * Returns `null` for anything else.
 *
 * @example
 * normalizeHexColor('F00');        // '#ff0000'
 * normalizeHexColor('#1A2b3C');    // '#1a2b3c'
 * normalizeHexColor('red');        // null
 */
export function normalizeHexColor(value: string): string | null {
    const hex = value.trim().replace(/^#/, '').toLowerCase();

    if (/^[0-9a-f]{3}$/.test(hex)) {
        return `#${[...hex].map((digit) => digit + digit).join('')}`;
    }

    return /^[0-9a-f]{6}$/.test(hex) ? `#${hex}` : null;
}

/** Bullet glyphs that start a list paragraph when a Word list arrives as text. */
const BULLET_PATTERN = /^\s*[•·▪◦●]\s+/;

/** A typed number (`1.`, `2)`) and the whitespace after it. */
const NUMBER_PATTERN = /^\s*(\d{1,3})[.)]\s+/;

/** The marker of a numbered Word item (`1.`, `a)`, `iv.`), as opposed to a bullet (`·`, `o`, `§`). */
const WORD_NUMBER_MARKER = /^\(?(\d{1,3}|[a-zA-Z]|[ivxlcdmIVXLCDM]+)[.)]$/;

type ListItem = { element: HTMLElement; kind: 'ul' | 'ol'; level: number; start?: number };

/** Removes the first `count` characters of the text of `root`, dropping the text nodes that become empty. */
function stripLeadingText(root: Node, count: number): void {
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    const nodes: Text[] = [];
    while (walker.nextNode()) {
        nodes.push(walker.currentNode as Text);
    }

    let left = count;
    for (const node of nodes) {
        if (left <= 0) {
            break;
        }
        const cut = Math.min(left, node.data.length);
        node.data = node.data.slice(cut);
        left -= cut;
        if (node.data === '') {
            node.remove();
        }
    }
}

/** The list level of a Word paragraph from `mso-list:l0 level2 lfo1`, or `null` when it is not a list paragraph. */
function wordListLevel(element: HTMLElement): number | null {
    const style = element.getAttribute('style') ?? '';
    const match = style.match(/mso-list\s*:\s*([^;]*)/i);

    if (!match || /^\s*(none|ignore)\b/i.test(match[1])) {
        return null;
    }

    return Number(match[1].match(/level(\d+)/i)?.[1] ?? 1);
}

/** Classifies a paragraph that Word marked as a list item (`mso-list`), removing the typed marker; `null` for other nodes. */
function wordMarkedItem(element: HTMLElement): ListItem | null {
    const level = wordListLevel(element);
    if (level === null) {
        return null;
    }

    const marker = [...element.querySelectorAll<HTMLElement>('[style]')].find((node) => /mso-list\s*:\s*ignore/i.test(node.getAttribute('style') ?? ''));
    const markerText = (marker?.textContent ?? '').replace(/\s+/g, '');
    marker?.remove();
    stripLeadingText(element, (element.textContent ?? '').match(/^\s*/)?.[0].length ?? 0);

    const kind = WORD_NUMBER_MARKER.test(markerText) ? 'ol' : 'ul';
    const number = Number(markerText.match(/^\(?(\d{1,3})[.)]$/)?.[1]);

    return { element, kind, level, start: kind === 'ol' && number > 1 ? number : undefined };
}

/** A paragraph whose text starts with a bullet glyph: Word list that lost its list markup (plain text). */
function glyphBulletItem(element: HTMLElement): ListItem | null {
    const match = (element.textContent ?? '').match(BULLET_PATTERN);
    if (!match) {
        return null;
    }
    stripLeadingText(element, match[0].length);

    return { element, kind: 'ul', level: 1 };
}

/**
 * Turns Word list paragraphs of pasted HTML into real nested `ul` / `ol` lists. Recognized, on top-level paragraphs:
 * Word's own list markup (`mso-list:l0 level2 lfo1` with the typed marker in a `mso-list:Ignore` span: the level, bullet
 * versus number), a paragraph that starts with a bullet glyph (`•`, `·`, `▪`, `◦`, `●`) and whitespace, and a run of at
 * least two consecutive paragraphs numbered `1.`, `2.`, `3.` (one more each time; a lone `2026. …` stays text). The typed
 * marker and the spaces after it are removed. Consecutive items form one list; any other node ends it. Each item is
 * `li > p`, as the editor expects (the server unwraps the `p` on save). HTML without such paragraphs is returned as is, so
 * lists from browsers and Google Docs keep the default paste.
 *
 * @example
 * convertWordLists('<p>•&nbsp;&nbsp;One</p><p>•&nbsp;&nbsp;Two</p>');   // '<ul><li><p>One</p></li><li><p>Two</p></li></ul>'
 * convertWordLists('<p>1. A</p><p>2. B</p>');                           // '<ol><li><p>A</p></li><li><p>B</p></li></ol>'
 * convertWordLists('<p>2026. A year</p>');                              // '<p>2026. A year</p>' (unchanged)
 */
export function convertWordLists(html: string): string {
    const body = new DOMParser().parseFromString(html, 'text/html').body;
    const nodes = [...body.childNodes];
    const items = new Map<Node, ListItem>();

    for (const node of nodes) {
        if (node instanceof HTMLElement && node.tagName === 'P') {
            const item = wordMarkedItem(node) ?? glyphBulletItem(node);
            if (item) {
                items.set(node, item);
            }
        }
    }

    // Typed numbers: only a run of two or more paragraphs numbered one after another is a list.
    const isBlank = (node: Node) => node.nodeType === Node.COMMENT_NODE || (node.nodeType === Node.TEXT_NODE && (node.textContent ?? '').trim() === '');
    let run: { element: HTMLElement; number: number }[] = [];
    const closeRun = () => {
        if (run.length >= 2) {
            for (const entry of run) {
                stripLeadingText(entry.element, (entry.element.textContent ?? '').match(NUMBER_PATTERN)![0].length);
                items.set(entry.element, { element: entry.element, kind: 'ol', level: 1, start: run[0].number > 1 ? run[0].number : undefined });
            }
        }
        run = [];
    };
    for (const node of nodes) {
        if (isBlank(node)) {
            continue;
        }
        const number = node instanceof HTMLElement && node.tagName === 'P' && !items.has(node) ? Number((node.textContent ?? '').match(NUMBER_PATTERN)?.[1]) : NaN;
        if (Number.isNaN(number)) {
            closeRun();
        } else if (run.length > 0 && number !== run[run.length - 1].number + 1) {
            closeRun();
            run.push({ element: node as HTMLElement, number });
        } else {
            run.push({ element: node as HTMLElement, number });
        }
    }
    closeRun();

    if (items.size === 0) {
        return html;
    }

    // Build the lists: a stack of open lists, one per nesting level.
    let stack: { list: HTMLElement; level: number }[] = [];
    for (const node of nodes) {
        const item = items.get(node);
        if (!item) {
            if (!isBlank(node)) {
                stack = [];
            } else if (stack.length > 0) {
                node.remove();
            }
            continue;
        }

        while (stack.length > 0 && stack[stack.length - 1].level > item.level) {
            stack.pop();
        }
        const top = stack[stack.length - 1];
        if (top && top.level === item.level && top.list.tagName.toLowerCase() !== item.kind) {
            stack.pop();
        }

        let current = stack[stack.length - 1];
        if (!current || current.level < item.level) {
            const list = document.createElement(item.kind);
            if (item.start) {
                list.setAttribute('start', String(item.start));
            }
            const parent = current?.list.lastElementChild;
            if (parent) {
                parent.appendChild(list);
            } else {
                node.parentNode?.insertBefore(list, node);
            }
            current = { list, level: item.level };
            stack.push(current);
        }

        const li = document.createElement('li');
        const paragraph = document.createElement('p');
        paragraph.append(...item.element.childNodes);
        li.appendChild(paragraph);
        current.list.appendChild(li);
        node.remove();
    }

    return body.innerHTML;
}

/**
 * The HTML for plain pasted text when it holds a list written with typed markers (`•    text`, `1.    text`, `2.    text`),
 * otherwise `null` (the default paste is used). Lines become paragraphs, then {@see convertWordLists} builds the lists.
 *
 * @example
 * convertPastedText('Intro\n•    One\n•    Two');   // '<p>Intro</p><ul><li><p>One</p></li><li><p>Two</p></li></ul>'
 * convertPastedText('Just a line');                  // null
 */
export function convertPastedText(text: string): string | null {
    const escape = (line: string) => line.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    const lines = text.split(/(?:\r\n?|\n)+/).filter((line) => line.trim() !== '');
    const html = lines.map((line) => `<p>${escape(line)}</p>`).join('');
    const converted = convertWordLists(html);

    return converted === html ? null : converted;
}

function toUrl(address: string): URL | null {
    try {
        return new URL(address);
    } catch {
        return null;
    }
}
