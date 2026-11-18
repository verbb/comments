import { beforeEach, describe, expect, it } from 'vitest';

import { dispatchLegacyEvent } from '../src/legacy-events.js';

// The names dispatched on the stand-in document, in order.
const fired: string[] = [];

beforeEach(() => {
    fired.length = 0;
    (globalThis as { document?: unknown }).document = {
        dispatchEvent: (event: Event) => {
            fired.push(event.type);

            return true;
        },
    };
});

// A thread's root, with or without the mount point's compatibility attribute.
const root = (compatible: boolean) => ({
    hasAttribute: (name: string) => compatible && name === 'data-comments-compatibility',
}) as unknown as HTMLElement;

describe('Comments 3 event names', () => {
    it('dispatches the Comments 3 name beside each event it had', () => {
        const thread = root(true);

        dispatchLegacyEvent(thread, 'comments:mounted', {});
        dispatchLegacyEvent(thread, 'comments:vote-changed', { vote: 'upvote' });
        dispatchLegacyEvent(thread, 'comments:vote-changed', { vote: 'downvote' });
        dispatchLegacyEvent(thread, 'comments:report-changed', { reported: true });
        dispatchLegacyEvent(thread, 'comments:follow-changed', { following: true });
        dispatchLegacyEvent(thread, 'comments:comment-updated', {});
        dispatchLegacyEvent(thread, 'comments:comment-deleted', {});

        expect(fired).toEqual(['comments:init', 'comments:upvote', 'comments:downvote', 'comments:flag', 'comments:subscribe', 'comments:edit-submit', 'comments:delete']);
    });

    it('tells a reply from a new comment by the editor that was opened', () => {
        const thread = root(true);

        dispatchLegacyEvent(thread, 'comments:editor-opened', { mode: 'reply' });
        dispatchLegacyEvent(thread, 'comments:comment-created', {});
        dispatchLegacyEvent(thread, 'comments:comment-created', {});
        dispatchLegacyEvent(thread, 'comments:editor-opened', { mode: 'edit' });
        dispatchLegacyEvent(thread, 'comments:editor-closed', { reason: 'cancel' });
        dispatchLegacyEvent(thread, 'comments:editor-closed', { reason: 'cancel' });

        expect(fired).toEqual(['comments:reply-open', 'comments:reply-submit', 'comments:submit', 'comments:edit-open', 'comments:edit-close']);
    });

    it('sends nothing for a vote taken back, which Comments 3 had no event for', () => {
        dispatchLegacyEvent(root(true), 'comments:vote-changed', { vote: null });

        expect(fired).toEqual([]);
    });

    it('sends nothing for a thread rendered without compatibility mode, or one drawn by an app', () => {
        dispatchLegacyEvent(root(false), 'comments:mounted', {});
        dispatchLegacyEvent(root(false), 'comments:vote-changed', { vote: 'upvote' });

        expect(fired).toEqual([]);
    });
});
