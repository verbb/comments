import type { CommentsClient } from './client.js';
import { CommentsRequestError } from './errors.js';
import type { ProtectionData, ProtectionEvidenceData } from './types.js';

export type EvidenceContext = {
    /** `upload` is a guest's file for a custom field, which uses a preparation up like a comment. */
    action: 'create' | 'update' | 'upload';
    parentUid?: string | null;
    uid?: string;
};

/**
 * Turns a preparation into evidence. The browser package mounts Protect on the form and returns
 * `prepare()`'s result; a headless app does the same with its own form.
 */
export type EvidenceProvider = (protection: ProtectionData, context: EvidenceContext) => Promise<ProtectionEvidenceData>;

export type ProtectionFlowOptions = {
    client: CommentsClient;
    ownerId: number;
    siteId?: number | null;
    /** The comment being edited, for an edit form's own preparation. */
    commentUid?: string | null;
    /** The share of a preparation's lifetime after which an active flow fetches the next. */
    refreshAt?: number;
};

export interface ProtectionFlow {
    /** The current preparation, if any. */
    current(): ProtectionData | null;
    /** Takes a preparation from elsewhere, such as the `viewer` read. Null means none was given. */
    accept(protection: ProtectionData | null): void;
    /** A preparation that's fresh and unused, fetching one if needed. Null when the site doesn't use protection here. */
    ensure(signal?: AbortSignal): Promise<ProtectionData | null>;
    /** Fetches a new preparation now. */
    refresh(signal?: AbortSignal): Promise<ProtectionData | null>;
    /** Fetches a new preparation if this one is past its refresh point, such as when a hidden tab becomes visible. */
    refreshIfStale(): Promise<void>;
    /** Keeps the preparation fresh on a timer, while a form is open. */
    activate(): void;
    deactivate(): void;
    /**
     * Hands out a fresh preparation and marks it used straight away, so two forms submitting
     * together never share one. `fresh` fetches a new one even if the current one is unused.
     */
    take(fresh?: boolean): Promise<ProtectionData | null>;
    /** Sends a write with evidence, retrying once with a new preparation after `protection_expired` or `protection_required`. */
    run<T>(send: (evidence: ProtectionEvidenceData | undefined) => Promise<T>, evidence: EvidenceProvider | undefined, context: EvidenceContext): Promise<T>;
    /** Called with each new preparation, so a mounted form can take it. */
    subscribe(listener: (protection: ProtectionData | null) => void): () => void;
    destroy(): void;
}

const RETRY_CODES = new Set(['protection_expired', 'protection_required']);

/** Milliseconds before a timed refresh that failed is tried again. */
const RETRY_AFTER_FAILURE = 30_000;

let parser: Promise<(value: unknown) => unknown> | null = null;

/**
 * Protect's own check of the manifest, which also notes the server's clock against this device's
 * at the moment it arrived, for the form that mounts it later. Protect's module is imported the
 * first time a preparation arrives rather than with the thread, so someone who only reads never
 * downloads it; a form imports the same module to mount it, so it reads the clock noted here.
 */
function valid(protection: ProtectionData): Promise<boolean> {
    parser ??= import('@verbb/protect').then((protect) => protect.parseManifest);

    return parser.then((parse) => (parse(protection.manifest), true)).catch(() => false);
}

/**
 * Keeps one form's Protect preparation usable in a page left open all day: refreshed at about 80%
 * of its lifetime while active, refreshed when stale, replaced after every submission because
 * preparations are single-use, and replaced once more when the server says it expired. A
 * `challenge_failed` or `possible_spam` answer is never retried automatically.
 */
export function createProtectionFlow(options: ProtectionFlowOptions): ProtectionFlow {
    const refreshAt = options.refreshAt ?? 0.8;
    const listeners = new Set<(protection: ProtectionData | null) => void>();
    let data: ProtectionData | null = null;
    // When it was received and how long it lasts, both on this device's clock, so a visitor's
    // wrong clock can't make it look expired or fresh.
    let receivedAt = 0;
    let lifetime = 0;
    let used = false;
    // The `protection` read said this site doesn't use protection here.
    let disabled = false;
    let active = false;
    let destroyed = false;
    let timer: ReturnType<typeof setTimeout> | undefined;
    let fetching: Promise<ProtectionData | null> | null = null;
    // A preparation taken from elsewhere while Protect's module loads, which writes wait for.
    let accepting: Promise<void> = Promise.resolve();

    const elapsed = () => Date.now() - receivedAt;
    const stale = () => !data || used || elapsed() >= lifetime * refreshAt;

    function store(next: ProtectionData | null, fromServer: boolean): void {
        data = next;
        used = false;
        disabled = fromServer && next === null;
        receivedAt = Date.now();

        if (next) {
            const issuedAt = typeof next.manifest.issuedAt === 'number' ? next.manifest.issuedAt : Math.floor(receivedAt / 1000);
            lifetime = Math.max(0, (next.expiresAt - issuedAt) * 1000);
        } else {
            lifetime = 0;
        }

        schedule();
        listeners.forEach((listener) => listener(next));
    }

    function schedule(): void {
        clearTimeout(timer);
        timer = undefined;

        if (!active || destroyed || !data || lifetime <= 0) {
            return;
        }

        timer = setTimeout(timedRefresh, Math.max(0, lifetime * refreshAt - elapsed()));
    }

    function timedRefresh(): void {
        // A failed refresh keeps the old preparation and tries again shortly, while the form is
        // open; a write in the meantime fetches its own.
        flow.refresh().catch(() => {
            if (active && !destroyed) timer = setTimeout(timedRefresh, RETRY_AFTER_FAILURE);
        });
    }

    const flow: ProtectionFlow = {
        current: () => data,
        accept(protection) {
            if (!protection) {
                return;
            }

            // One that isn't valid is ignored, so the next write fetches its own.
            accepting = accepting.then(() => valid(protection)).then((ok) => {
                if (ok && !destroyed) store(protection, false);
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

                    if (!destroyed) store(response.protection, true);
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

            const submit = async (protection: ProtectionData | null) => send(protection ? await evidence(protection, context) : undefined);

            try {
                return await submit(await flow.take());
            } catch (error) {
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
