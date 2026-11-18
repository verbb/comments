import type { CommentsClient } from './client.js';
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
/**
 * Keeps one form's Protect preparation usable in a page left open all day: refreshed at about 80%
 * of its lifetime while active, refreshed when stale, replaced after every submission because
 * preparations are single-use, and replaced once more when the server says it expired. A
 * `challenge_failed` or `possible_spam` answer is never retried automatically.
 */
export declare function createProtectionFlow(options: ProtectionFlowOptions): ProtectionFlow;
