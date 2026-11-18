import { CommentsRequestError } from './errors.js';
const RETRY_CODES = new Set(['protection_expired', 'protection_required']);
/** Milliseconds before a timed refresh that failed is tried again. */
const RETRY_AFTER_FAILURE = 30_000;
let parser = null;
/**
 * Protect's own check of the manifest, which also notes the server's clock against this device's
 * at the moment it arrived, for the form that mounts it later. Protect's module is imported the
 * first time a preparation arrives rather than with the thread, so someone who only reads never
 * downloads it; a form imports the same module to mount it, so it reads the clock noted here.
 */
function valid(protection) {
    parser ??= import('@verbb/protect').then((protect) => protect.parseManifest);
    return parser.then((parse) => (parse(protection.manifest), true)).catch(() => false);
}
/**
 * Keeps one form's Protect preparation usable in a page left open all day: refreshed at about 80%
 * of its lifetime while active, refreshed when stale, replaced after every submission because
 * preparations are single-use, and replaced once more when the server says it expired. A
 * `challenge_failed` or `possible_spam` answer is never retried automatically.
 */
export function createProtectionFlow(options) {
    const refreshAt = options.refreshAt ?? 0.8;
    const listeners = new Set();
    let data = null;
    // When it was received and how long it lasts, both on this device's clock, so a visitor's
    // wrong clock can't make it look expired or fresh.
    let receivedAt = 0;
    let lifetime = 0;
    let used = false;
    // The `protection` read said this site doesn't use protection here.
    let disabled = false;
    let active = false;
    let destroyed = false;
    let timer;
    let fetching = null;
    // A preparation taken from elsewhere while Protect's module loads, which writes wait for.
    let accepting = Promise.resolve();
    const elapsed = () => Date.now() - receivedAt;
    const stale = () => !data || used || elapsed() >= lifetime * refreshAt;
    function store(next, fromServer) {
        data = next;
        used = false;
        disabled = fromServer && next === null;
        receivedAt = Date.now();
        if (next) {
            const issuedAt = typeof next.manifest.issuedAt === 'number' ? next.manifest.issuedAt : Math.floor(receivedAt / 1000);
            lifetime = Math.max(0, (next.expiresAt - issuedAt) * 1000);
        }
        else {
            lifetime = 0;
        }
        schedule();
        listeners.forEach((listener) => listener(next));
    }
    function schedule() {
        clearTimeout(timer);
        timer = undefined;
        if (!active || destroyed || !data || lifetime <= 0) {
            return;
        }
        timer = setTimeout(timedRefresh, Math.max(0, lifetime * refreshAt - elapsed()));
    }
    function timedRefresh() {
        // A failed refresh keeps the old preparation and tries again shortly, while the form is
        // open; a write in the meantime fetches its own.
        flow.refresh().catch(() => {
            if (active && !destroyed)
                timer = setTimeout(timedRefresh, RETRY_AFTER_FAILURE);
        });
    }
    const flow = {
        current: () => data,
        accept(protection) {
            if (!protection) {
                return;
            }
            // One that isn't valid is ignored, so the next write fetches its own.
            accepting = accepting.then(() => valid(protection)).then((ok) => {
                if (ok && !destroyed)
                    store(protection, false);
            });
        },
        async ensure(signal) {
            await accepting;
            if (disabled) {
                return null;
            }
            return stale() ? flow.refresh(signal) : data;
        },
        refresh(signal) {
            if (destroyed) {
                return Promise.reject(new Error('Comments: this protection flow was destroyed.'));
            }
            fetching ??= options.client.protection({ ownerId: options.ownerId, siteId: options.siteId, commentUid: options.commentUid }, { signal })
                .then(async (response) => {
                if (response.protection && !await valid(response.protection)) {
                    throw CommentsRequestError.from('invalid_response', 'Comment verification is unavailable. Please try again.', { recoverable: true });
                }
                if (!destroyed)
                    store(response.protection, true);
                return response.protection;
            })
                .finally(() => { fetching = null; });
            return fetching;
        },
        async take(fresh = false) {
            let next = fresh ? await flow.refresh() : await flow.ensure();
            // Another form may have taken it while this one waited, so fetch another.
            for (let tries = 0; next && (next !== data || used) && tries < 3; tries++) {
                next = await flow.refresh();
            }
            if (!next) {
                return null;
            }
            if (next !== data || used) {
                throw CommentsRequestError.from('protection_unavailable', 'Comment verification is unavailable. Please try again.', { recoverable: true });
            }
            used = true;
            return next;
        },
        async refreshIfStale() {
            if ((data || active) && stale()) {
                await flow.refresh();
            }
        },
        activate() {
            active = true;
            schedule();
        },
        deactivate() {
            active = false;
            schedule();
        },
        async run(send, evidence, context) {
            if (!evidence) {
                return send(undefined);
            }
            const submit = async (protection) => send(protection ? await evidence(protection, context) : undefined);
            try {
                return await submit(await flow.take());
            }
            catch (error) {
                if (!(error instanceof CommentsRequestError) || !RETRY_CODES.has(error.code)) {
                    throw error;
                }
                return submit(await flow.take(true));
            }
        },
        subscribe(listener) {
            listeners.add(listener);
            return () => listeners.delete(listener);
        },
        destroy() {
            destroyed = true;
            active = false;
            clearTimeout(timer);
            listeners.clear();
        },
    };
    return flow;
}
