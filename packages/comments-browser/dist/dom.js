/**
 * Small page helpers shared by the thread and its forms.
 */
const DRAFT_PREFIX = 'comments-draft:';
const DRAFT_VERSION = 1;
// Storage can be full or blocked, and the editor still works without drafts.
export function readDraft(key) {
    try {
        const draft = JSON.parse(localStorage.getItem(DRAFT_PREFIX + key) ?? 'null');
        if (!draft || typeof draft !== 'object')
            return null;
        const { version, savedAt, text } = draft;
        return version === DRAFT_VERSION && typeof savedAt === 'number' && typeof text === 'string' ? { savedAt, text } : null;
    }
    catch {
        return null;
    }
}
export function writeDraft(key, text) {
    try {
        if (text === null) {
            localStorage.removeItem(DRAFT_PREFIX + key);
        }
        else {
            localStorage.setItem(DRAFT_PREFIX + key, JSON.stringify({ version: DRAFT_VERSION, savedAt: Date.now(), text }));
        }
    }
    catch {
        // See readDraft().
    }
}
/** Whether Editor kept a draft under this key, which only Editor itself can bring back. */
export function hasEditorDraft(key) {
    try {
        return Boolean(localStorage.getItem(`verbb-editor-draft:${key}`));
    }
    catch {
        return false;
    }
}
export function isMac() {
    const nav = navigator;
    return /mac|iphone|ipad|ipod/i.test(nav.userAgentData?.platform ?? nav.platform ?? '');
}
/** "5 minutes ago", in the page's language, for a draft's age. */
export function relativeTime(timestamp) {
    // At least a minute, so a draft from a moment ago doesn't read as "from now".
    const minutes = Math.min(-1, Math.round((timestamp - Date.now()) / 60000));
    let formatter;
    // Some sites set a `lang` that isn't a valid language tag, such as `en_US`.
    try {
        formatter = new Intl.RelativeTimeFormat(document.documentElement.lang || undefined, { numeric: 'auto' });
    }
    catch {
        formatter = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });
    }
    if (Math.abs(minutes) < 60)
        return formatter.format(minutes, 'minute');
    if (Math.abs(minutes) < 60 * 24)
        return formatter.format(Math.round(minutes / 60), 'hour');
    return formatter.format(Math.round(minutes / (60 * 24)), 'day');
}
export function prefersReducedMotion() {
    return typeof matchMedia === 'function' && matchMedia('(prefers-reduced-motion: reduce)').matches;
}
/** Something visible, so focus doesn't land on a hidden element. */
export function isShown(element) {
    return element.getClientRects().length > 0;
}
// The chunk a failed `import()` names. Chromium's and Firefox's messages do; Safari's doesn't, but
// Safari fetches a failed chunk again anyway. A bundler's own chunk loader says something else.
function failedChunk(error) {
    return /dynamically imported module: (\S+)/.exec(String(error?.message))?.[1];
}
/**
 * Imports a chunk again after it failed to load, for someone pressing **Try again**. Chromium keeps
 * a failed module fetch for the life of the page (whatwg/html#6768), so importing it again fails
 * without asking the server. When it fails again and the server now answers for the chunk, only a
 * new page can load it, so the page reloads. That's also what fixes a tab left open across an
 * update, whose chunks have gone. Without an answer, such as when offline, it fails as before.
 * The startup loader keeps its own copy, as nothing the renderer imports may live in it.
 */
export function importAgain(load) {
    return load().catch(async (error) => {
        const url = failedChunk(error);
        if (url && await fetch(url, { method: 'HEAD', cache: 'no-store' }).then(() => true, () => false)) {
            location.reload();
            return new Promise(() => undefined);
        }
        throw error;
    });
}
/**
 * Like `importAgain()`, without reloading the page: when importing again fails, the chunk is
 * imported from its address with a query added, which Chromium hasn't seen fail. Only for a chunk
 * no other chunk imports, such as the file tray, as one importing it by its own address would
 * still fail, and for one whose page has something worth keeping, such as a comment being written.
 */
export function importFresh(load) {
    return load().catch((error) => {
        const url = failedChunk(error);
        if (!url)
            throw error;
        const fresh = new URL(url);
        fresh.searchParams.set('retry', String(Date.now()));
        return import(/* @vite-ignore */ /* webpackIgnore: true */ fresh.href);
    });
}
