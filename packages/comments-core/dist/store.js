import { CommentsRequestError, GENERIC_MESSAGE, isCommentsRequestError } from './errors.js';
import { createOperationId } from './operation.js';
import { createProtectionFlow } from './protection.js';
import { COMMENTS_API_VERSION } from './version.js';
const HIDDEN_STATES = new Set(['removed', 'spam']);
/** Comments asked about in one `viewer` read, below the server's limit of 200 and a URL's length. */
const VIEWER_BATCH = 100;
/** The number in a comment's permalink as text, `1446`, as the browser events give it, or null. */
function idOf(comment) {
    const number = comment ? numberOf(comment) : null;
    return number === null ? null : String(number);
}
/** A `comments:request-error` detail, with the same keys as the browser event's. */
function failure(reason, error) {
    return isCommentsRequestError(error)
        ? { reason, message: error.message, category: error.category, code: error.code, fieldErrors: error.fieldErrors, recoverable: error.recoverable, error }
        : { reason, message: GENERIC_MESSAGE, error };
}
/** The number in a comment's permalink, `#comment-1446`, which links use. */
function numberOf(comment) {
    const match = /#comment-(\d+)$/.exec(comment.permalink ?? '');
    return match ? Number(match[1]) : null;
}
/** The comments to show at the root, in order. */
export function selectRoots(state) {
    return state.roots.flatMap((uid) => state.comments[uid] ?? []);
}
/** One comment's loaded replies, in order. */
export function selectReplies(state, uid) {
    return (state.replies[uid]?.uids ?? []).flatMap((reply) => state.comments[reply] ?? []);
}
export function initialThreadState(sort = 'newest', unavailable = []) {
    return {
        status: 'idle',
        error: null,
        sort,
        threadVersion: null,
        conversation: null,
        editor: null,
        labels: {},
        locale: null,
        comments: {},
        roots: [],
        next: null,
        loadingMore: false,
        replies: {},
        viewer: null,
        viewerEditor: null,
        permissions: {},
        moderation: null,
        busy: {},
        unavailable,
    };
}
/**
 * A thread's state and actions over a client, with no DOM. Pages are merged by UID, so a comment
 * that moves between pages under the top sort appears once. Responses that arrive after the sort
 * changed, after the person signed out, or after `destroy()`, are dropped.
 *
 * Actions on the same thing run one at a time, in the order they were asked for. Asking again
 * with the same input while one is waiting or running returns that promise, so a double click
 * sends once; a different input, such as an unlike straight after a like, waits its turn and
 * then runs, so the last thing the person did is what the server ends up with. A write still
 * running at `destroy()` finishes and resolves for its caller without changing the state, and
 * one still waiting is rejected.
 */
export function createThreadStore(client, options = {}) {
    const unavailable = [...(client.auth.unavailable ?? [])];
    let state = initialThreadState(options.sort, unavailable);
    const listeners = new Set();
    const handlers = new Map();
    const queues = new Map();
    const flows = new Map();
    const withViewer = options.viewer ?? true;
    // Bumped on each reload, so a response for an earlier sort or load is ignored.
    let generation = 0;
    // Bumped when the person signs out, so a private answer for the old identity is ignored.
    let identity = 0;
    let destroyed = false;
    let refreshes = 0;
    // Comments only private data has shown, such as a moderator's held or reported ones outside
    // the public window, which go when the person signs out.
    const privateOnly = new Set();
    // Comments a deep link couldn't find in public data, asked of the next `viewer` read, which
    // returns the person's own waiting comment or a moderator's held one from anywhere.
    const wanted = new Set();
    // The same, for links by the number in a comment's permalink.
    const wantedIds = new Set();
    // The page size the server used for the first page, which later pages must keep, because a
    // cursor is only valid at the limit it was made with.
    let pageLimit;
    const stopListening = client.auth.onReset?.(() => {
        identity++;
        const draft = clone(state);
        clearPrivate(draft);
        set(draft);
        if (state.status === 'ready')
            store.refreshViewer().catch(() => undefined);
    });
    function set(next) {
        if (destroyed)
            return;
        state = { ...state, ...next };
        if (unavailable.length && (next.permissions || next.viewer))
            state = restrict(clone(state));
        listeners.forEach((listener) => listener(state));
    }
    function emit(name, detail) {
        const full = { apiVersion: COMMENTS_API_VERSION, ...detail };
        handlers.get(name)?.forEach((handler) => handler(full));
    }
    function owner() {
        return {
            ownerId: options.ownerId ?? state.conversation?.ownerId,
            ownerUri: options.ownerId === undefined && !state.conversation ? options.ownerUri : undefined,
            siteId: options.siteId ?? state.conversation?.siteId ?? client.siteId,
        };
    }
    function requireOwnerId(draft = state) {
        const ownerId = options.ownerId ?? draft.conversation?.ownerId;
        if (ownerId === undefined) {
            throw new Error('Comments: load the thread first, so its owner is known.');
        }
        return ownerId;
    }
    /**
     * Runs actions one at a time per key, such as `vote:<uid>`, marking the key busy and reporting
     * each request. The same `signature` as the last one queued returns its promise.
     */
    function queue(key, signature, action, run) {
        const last = queues.get(key);
        if (last && last.signature === signature) {
            return last.promise;
        }
        set({ busy: { ...state.busy, [key]: true } });
        const settled = () => undefined;
        const start = () => {
            if (destroyed) {
                return Promise.reject(CommentsRequestError.from('invalid_request', GENERIC_MESSAGE, { detail: 'store destroyed' }));
            }
            emit('comments:request-start', { reason: action });
            return run().then((result) => {
                emit('comments:request-end', { reason: action });
                return result;
            }, (error) => {
                emit('comments:request-end', { reason: action });
                emit('comments:request-error', failure(action, error));
                throw error;
            });
        };
        // With nothing ahead of it, an action starts straight away.
        const promise = (last ? last.promise.then(settled, settled).then(start) : start()).finally(() => {
            // Something queued behind keeps the key busy.
            if (queues.get(key)?.promise !== promise)
                return;
            queues.delete(key);
            const busy = { ...state.busy };
            delete busy[key];
            set({ busy });
        });
        queues.set(key, { signature, promise });
        return promise;
    }
    /** A comment, and its nested previews, came in public data. */
    function markPublic(comment) {
        privateOnly.delete(comment.uid);
        comment.replies.items.forEach(markPublic);
    }
    /** Forgets queued work under these keys, such as loads for a sort that's no longer wanted. */
    function forget(...keys) {
        const busy = { ...state.busy };
        for (const key of keys) {
            queues.delete(key);
            delete busy[key];
        }
        set({ busy });
    }
    async function loadPage(cursor) {
        // A first page starts a new generation; a later page belongs to the current one.
        const current = cursor ? generation : ++generation;
        const asked = identity;
        const query = { ...owner(), sort: state.sort, limit: cursor ? pageLimit ?? options.limit : options.limit, cursor };
        const asking = [...wanted];
        const askingIds = [...wantedIds];
        let thread;
        let viewer;
        try {
            [thread, viewer] = await Promise.all([
                client.thread(query),
                withViewer ? client.viewer({ ...query, uids: asking.length ? asking : undefined, ids: askingIds.length ? askingIds : undefined }).catch((error) => {
                    // The thread still shows without the viewer's data; the error is reported.
                    emit('comments:request-error', failure('viewer', error));
                    return null;
                }) : null,
            ]);
            // A cache served a thread older than the data the viewer read saw, such as one without
            // the visitor's own new comment. Ask once more, past the browser's copy and at a URL a
            // CDN hasn't seen; whatever comes back is drawn, so a stubborn cache can't loop.
            // If that fails (some edge platforms refuse `cache`), the older thread is still drawn.
            if (viewer && viewer.threadVersion !== thread.threadVersion) {
                thread = await client.thread({ ...query, threadVersion: viewer.threadVersion }, { cache: 'no-cache' }).catch((error) => {
                    emit('comments:request-error', failure('thread', error));
                    return thread;
                });
            }
        }
        catch (error) {
            if (current !== generation || destroyed)
                return;
            throw error;
        }
        if (current !== generation || destroyed)
            return;
        if (!cursor)
            pageLimit = thread.page.limit;
        if (viewer) {
            asking.forEach((uid) => wanted.delete(uid));
            askingIds.forEach((id) => wantedIds.delete(id));
        }
        const draft = cursor ? clone(state) : { ...initialThreadState(state.sort, unavailable), busy: state.busy };
        if (!cursor)
            privateOnly.clear();
        draft.status = 'ready';
        draft.error = null;
        draft.threadVersion = thread.threadVersion;
        draft.conversation = thread.conversation;
        draft.editor = thread.editor;
        draft.labels = thread.labels;
        draft.locale = thread.locale;
        draft.next = thread.page.next;
        draft.loadingMore = false;
        for (const comment of thread.comments) {
            if (!draft.comments[comment.uid] && !draft.roots.includes(comment.uid)) {
                draft.roots.push(comment.uid);
            }
            ingest(draft, comment);
            markPublic(comment);
        }
        // A viewer answer for someone who has since signed out is left out.
        if (viewer && asked !== identity)
            viewer = null;
        if (viewer)
            mergeViewer(draft, viewer);
        set(draft);
        emit('comments:roots-loaded', { cursor, count: thread.comments.length });
        if (viewer)
            emit('comments:viewer-loaded', { viewer: draft.viewer });
    }
    function mergeViewer(draft, viewer) {
        draft.viewer = viewer.viewer;
        draft.viewerEditor = viewer.editor;
        draft.moderation = viewer.moderation;
        draft.permissions = { ...draft.permissions, ...viewer.comments };
        if (viewer.editor.protection)
            flow(undefined, draft).accept(viewer.editor.protection);
        for (const extra of viewer.extraComments) {
            const known = Boolean(draft.comments[extra.uid]);
            ingest(draft, extra);
            if (known)
                continue;
            privateOnly.add(extra.uid);
            if (extra.parentUid === null) {
                insertSorted(draft.roots, extra, draft);
            }
            else if (draft.comments[extra.parentUid]) {
                const replies = repliesOf(draft, extra.parentUid);
                // Replies run oldest first. A placeholder has no date to compare.
                const index = replies.uids.findIndex((uid) => draft.comments[uid]?.createdAt && compareDates(draft.comments[uid], extra) > 0);
                replies.uids.splice(index === -1 ? replies.uids.length : index, 0, extra.uid);
            }
            else {
                // A held reply whose parent isn't loaded has nowhere to show yet.
                delete draft.comments[extra.uid];
            }
        }
    }
    /**
     * Takes out what only the person asking could see: comments in states the public data never
     * has (their own pending ones, a moderator's held ones), reported comments back to approved,
     * moderation details and permissions. With `uids`, only for those comments; without, everything
     * private, as after signing out.
     */
    function clearPrivate(draft, uids) {
        const only = uids ? new Set(uids) : null;
        for (const [uid, comment] of Object.entries(draft.comments)) {
            if (only && !only.has(uid))
                continue;
            if (comment.state === 'pending' || comment.state === 'held' || privateOnly.has(uid)) {
                removeLocally(draft, uid);
                privateOnly.delete(uid);
            }
            else if (comment.state === 'reported' || comment.moderation) {
                const { moderation: _moderation, ...rest } = comment;
                draft.comments[uid] = { ...rest, state: comment.state === 'reported' ? 'approved' : comment.state };
            }
            delete draft.permissions[uid];
        }
        if (!only) {
            draft.permissions = {};
            draft.viewer = null;
            draft.viewerEditor = null;
            draft.moderation = null;
        }
    }
    /** Takes away what the sign-in never allows, whatever the server's answer said. */
    function restrict(draft) {
        const noEdit = unavailable.includes('edit');
        const noModeration = unavailable.includes('moderate');
        if (!noEdit && !noModeration)
            return draft;
        for (const [uid, permissions] of Object.entries(draft.permissions)) {
            draft.permissions[uid] = { ...permissions, ...(noEdit ? { canEdit: false } : {}), ...(noModeration ? { moderation: null } : {}) };
        }
        if (noModeration) {
            if (draft.viewer)
                draft.viewer = { ...draft.viewer, isModerator: false };
            draft.moderation = null;
        }
        return draft;
    }
    function flow(uid, draft = state) {
        const key = uid ?? '';
        let existing = flows.get(key);
        if (!existing) {
            const siteId = options.siteId ?? draft.conversation?.siteId ?? client.siteId;
            existing = createProtectionFlow({ client, ownerId: requireOwnerId(draft), siteId, commentUid: uid ?? null });
            flows.set(key, existing);
        }
        return existing;
    }
    function applyPatch(patch) {
        const draft = clone(state);
        const comment = draft.comments[patch.uid];
        if (comment && patch.interaction) {
            draft.comments[patch.uid] = { ...comment, interaction: patch.interaction };
        }
        if (patch.viewer) {
            draft.permissions[patch.uid] = patch.viewer;
        }
        return draft;
    }
    function removeLocally(draft, uid) {
        const comment = draft.comments[uid];
        if (!comment)
            return;
        const replies = draft.replies[uid];
        // A comment with replies stays as a placeholder, so its replies keep their place.
        if (replies && (replies.count > 0 || replies.uids.length > 0)) {
            draft.comments[uid] = { ...comment, state: 'placeholder', content: null, author: null, fields: [], custom: {} };
            return;
        }
        delete draft.comments[uid];
        delete draft.permissions[uid];
        delete draft.replies[uid];
        if (comment.parentUid === null) {
            draft.roots = draft.roots.filter((root) => root !== uid);
            if (draft.conversation && comment.state === 'approved')
                draft.conversation = { ...draft.conversation, rootCount: Math.max(0, draft.conversation.rootCount - 1) };
        }
        else if (draft.replies[comment.parentUid]) {
            const parent = repliesOf(draft, comment.parentUid);
            parent.uids = parent.uids.filter((reply) => reply !== uid);
            if (comment.state === 'approved')
                parent.count = Math.max(0, parent.count - 1);
        }
    }
    const store = {
        getState: () => state,
        subscribe(listener) {
            listeners.add(listener);
            return () => listeners.delete(listener);
        },
        on(name, listener) {
            const group = handlers.get(name) ?? new Set();
            group.add(listener);
            handlers.set(name, group);
            return () => group.delete(listener);
        },
        load() {
            set({ status: 'loading', error: null });
            return queue('load', state.sort, 'thread', () => loadPage(null)).catch((error) => {
                const failure = error instanceof CommentsRequestError
                    ? error
                    : CommentsRequestError.from('server_error', GENERIC_MESSAGE, { cause: error, detail: String(error) });
                set({ status: 'error', error: failure });
                throw failure;
            });
        },
        async loadMore() {
            if (!state.next || state.status !== 'ready')
                return;
            const cursor = state.next;
            set({ loadingMore: true });
            try {
                await queue('loadMore', cursor, 'thread', () => loadPage(cursor));
            }
            catch (error) {
                // A cursor the server no longer accepts, such as after a change of page size or
                // sort elsewhere: start the thread again rather than show a broken page.
                if (!(error instanceof CommentsRequestError) || error.code !== 'invalid_cursor')
                    throw error;
                forget('load', 'loadMore');
                await store.load();
            }
            finally {
                set({ loadingMore: false });
            }
        },
        setSort(sort) {
            if (sort === state.sort && state.status === 'ready')
                return Promise.resolve();
            set({ sort });
            emit('comments:sorted', { sort });
            // Loads for the old sort are dropped by the generation check, so neither may stand in
            // for one under the new sort.
            forget('load', 'loadMore');
            return store.load();
        },
        loadReplies(uid) {
            return queue(`replies:${uid}`, '', 'replies', async () => {
                const current = generation;
                const before = state.replies[uid];
                setReplies(uid, { loading: true });
                try {
                    const response = await client.replies({ parentUid: uid, cursor: before?.next ?? null });
                    if (current !== generation || destroyed)
                        return;
                    const draft = clone(state);
                    const replies = repliesOf(draft, uid);
                    const added = [];
                    for (const reply of response.replies.items) {
                        if (!replies.uids.includes(reply.uid)) {
                            replies.uids.push(reply.uid);
                            added.push(reply.uid);
                        }
                        ingest(draft, reply);
                        markPublic(reply);
                    }
                    replies.count = response.replies.count;
                    replies.next = response.replies.next;
                    replies.loading = false;
                    set(draft);
                    emit('comments:replies-loaded', { commentUid: uid, count: added.length });
                    // The viewer's permissions for the new replies and the previews nested in them.
                    const hydrating = response.replies.items.filter((reply) => added.includes(reply.uid)).flatMap(treeUids);
                    if (hydrating.length && state.viewer)
                        await hydrate(current, hydrating);
                }
                finally {
                    if (state.replies[uid]?.loading)
                        setReplies(uid, { loading: false });
                }
            });
        },
        loadComment(target, prefetched) {
            const ref = typeof target === 'string' ? { uid: target } : target;
            const key = 'uid' in ref ? ref.uid : `#${ref.id}`;
            return queue(`comment:${key}`, '', 'comment', async () => {
                const current = generation;
                let response = prefetched;
                if (!response) {
                    try {
                        if (prefetched === null)
                            throw CommentsRequestError.from('not_found', GENERIC_MESSAGE);
                        response = await client.comment(ref);
                    }
                    catch (error) {
                        // Not public: perhaps the person's own waiting comment, or one a moderator may
                        // see. The viewer read finds it from anywhere in this thread and places it.
                        if (!(error instanceof CommentsRequestError) || error.code !== 'not_found' || !withViewer)
                            throw error;
                        if ('uid' in ref)
                            wanted.add(ref.uid);
                        else
                            wantedIds.add(ref.id);
                        if (state.status === 'ready')
                            await hydrate(current, []);
                        const found = 'uid' in ref ? Boolean(state.comments[ref.uid]) : Object.values(state.comments).some((comment) => numberOf(comment) === ref.id);
                        if (!found && state.status === 'ready')
                            throw error;
                        return;
                    }
                }
                if (current !== generation || destroyed)
                    return;
                const draft = clone(state);
                const root = response.comment;
                // The root, with the replies leading to the target nested in its previews.
                if (!draft.comments[root.uid])
                    insertSorted(draft.roots, root, draft);
                ingest(draft, root);
                markPublic(root);
                set(draft);
                await hydrate(current, treeUids(root));
            });
        },
        refreshViewer() {
            // Every call reads again, since the person may have signed in or out in between.
            return queue('viewer', String(++refreshes), 'viewer', () => hydrate(generation, Object.keys(state.comments), options.limit, true));
        },
        create(input, writeOptions = {}) {
            const parentUid = input.parentUid ?? null;
            return queue(`create:${parentUid ?? 'root'}`, signature([input.content, input.guest, input.fields, writeOptions.operationId]), 'create', async () => {
                const ownerId = input.ownerId ?? requireOwnerId();
                // Fixed before the first attempt, so a retry with new protection is the same write.
                const request = { ...writeOptions, operationId: writeOptions.operationId ?? createOperationId() };
                const response = await flow().run((protection) => client.create({ ...input, ownerId, siteId: input.siteId ?? owner().siteId, parentUid, protection }, request), writeOptions.evidence ?? options.evidence, { action: 'create', parentUid });
                const draft = clone(state);
                const known = Boolean(draft.comments[response.comment.uid]);
                ingest(draft, response.comment);
                // A replay of a comment already here changes nothing twice.
                if (!known) {
                    const approved = response.moderationState === 'approved';
                    if (parentUid === null) {
                        if (draft.sort === 'newest')
                            draft.roots.unshift(response.comment.uid);
                        else
                            draft.roots.push(response.comment.uid);
                        if (draft.conversation && approved)
                            draft.conversation = { ...draft.conversation, rootCount: draft.conversation.rootCount + 1 };
                    }
                    else {
                        const replies = repliesOf(draft, parentUid);
                        replies.uids.push(response.comment.uid);
                        if (approved)
                            replies.count += 1;
                    }
                    if (draft.conversation) {
                        const commentCount = response.conversation?.commentCount ?? draft.conversation.commentCount + (approved ? 1 : 0);
                        draft.conversation = { ...draft.conversation, commentCount };
                    }
                }
                set(draft);
                emit('comments:comment-created', { commentUid: response.comment.uid, parentUid: parentUid ?? null, moderationState: response.moderationState, replayed: response.replayed });
                return response;
            });
        },
        update(uid, input, writeOptions = {}) {
            if (unavailable.includes('edit'))
                return Promise.reject(notAllowed());
            return queue(`update:${uid}`, signature([input.content, input.fields, writeOptions.operationId]), 'update', async () => {
                const request = { ...writeOptions, operationId: writeOptions.operationId ?? createOperationId() };
                const response = await flow(uid).run((protection) => client.update({ uid, ...input, protection }, request), writeOptions.evidence ?? options.evidence, { action: 'update', uid });
                const draft = clone(state);
                ingest(draft, response.comment);
                set(draft);
                emit('comments:comment-updated', { commentUid: uid });
                return response;
            });
        },
        delete(uid, writeOptions) {
            return queue(`delete:${uid}`, signature([writeOptions?.operationId]), 'delete', async () => {
                const response = await client.delete({ uid }, writeOptions);
                const draft = clone(state);
                // Counted by what this store shows rather than `replayed`: a replay usually means
                // the first answer was lost, so the comment is still here to count.
                const wasApproved = draft.comments[uid]?.state === 'approved';
                removeLocally(draft, uid);
                if (draft.conversation) {
                    const commentCount = response.conversation?.commentCount ?? Math.max(0, draft.conversation.commentCount - (wasApproved ? 1 : 0));
                    draft.conversation = { ...draft.conversation, commentCount };
                }
                set(draft);
                emit('comments:comment-deleted', { commentUid: uid });
                return response;
            });
        },
        vote(uid, vote, writeOptions) {
            return queue(`vote:${uid}`, signature([vote, writeOptions?.operationId]), 'vote', async () => {
                const response = await client.vote({ uid, vote }, writeOptions).catch(refused('vote', uid));
                set(applyPatch(response.comment));
                emit('comments:vote-changed', { commentUid: uid, commentId: idOf(state.comments[uid]), vote });
                return response;
            });
        },
        report: (uid, input, writeOptions) => report(uid, { reason: input.reason, details: input.details ?? null }, writeOptions),
        withdrawReport: (uid, writeOptions) => report(uid, { withdraw: true }, writeOptions),
        follow(following, email, writeOptions) {
            return queue('follow', signature([following, email, writeOptions?.operationId]), 'follow', async () => {
                const response = await client.follow({ ownerId: requireOwnerId(), siteId: owner().siteId, following, email }, writeOptions).catch(refused('follow'));
                if (state.viewer) {
                    set({ viewer: { ...state.viewer, follow: response.viewer.follow } });
                }
                emit('comments:follow-changed', { following: response.viewer.follow.following });
                return response;
            });
        },
        moderate(uid, action, input = {}, writeOptions) {
            if (unavailable.includes('moderate'))
                return Promise.reject(notAllowed());
            return queue(`moderate:${uid}`, signature([action, input.reason, input.note, writeOptions?.operationId]), 'moderate', async () => {
                const response = await client.moderate({ uid, action, reason: input.reason ?? null, note: input.note ?? null }, writeOptions);
                const draft = clone(state);
                const before = draft.comments[uid];
                const after = response.comment;
                // By what this store showed before and after, not `replayed`, and only for a comment it had.
                const delta = before ? (after.state === 'approved' ? 1 : 0) - (before.state === 'approved' ? 1 : 0) : 0;
                if (HIDDEN_STATES.has(after.state) || !('content' in after)) {
                    removeLocally(draft, uid);
                }
                else {
                    ingest(draft, after);
                }
                if (draft.conversation && delta) {
                    draft.conversation = { ...draft.conversation, commentCount: Math.max(0, draft.conversation.commentCount + delta) };
                }
                set(draft);
                emit('comments:comment-moderated', { commentUid: uid, commentId: idOf(before), moderationAction: action, state: after.state });
                return response;
            });
        },
        protection: (uid) => flow(uid),
        destroy() {
            destroyed = true;
            generation++;
            stopListening?.();
            flows.forEach((item) => item.destroy());
            flows.clear();
            listeners.clear();
            handlers.clear();
        },
    };
    /**
     * Reads the viewer for these comments and replaces what was private about them, unless the
     * thread reloaded or the person signed out meanwhile. `everything` replaces all private state,
     * for a refresh after signing in or out.
     */
    async function hydrate(current, uids, limit, everything = false) {
        if (!withViewer)
            return;
        const asked = identity;
        const asking = [...wanted];
        const askingIds = [...wantedIds];
        const all = [...new Set([...uids, ...asking])];
        // A whole loaded tree can be more than one read may ask about, or than a URL holds, so it's
        // asked in batches of UIDs and numbers together; the numbers go with the first.
        const batches = [];
        const first = Math.max(0, VIEWER_BATCH - askingIds.length);
        batches.push(all.slice(0, first));
        for (let start = first; start < all.length; start += VIEWER_BATCH)
            batches.push(all.slice(start, start + VIEWER_BATCH));
        const viewers = await Promise.all(batches.map((batch, index) => client.viewer({
            ...owner(), sort: state.sort, limit, uids: batch, ids: index === 0 && askingIds.length ? askingIds : undefined,
        })));
        if (current !== generation || asked !== identity || destroyed)
            return;
        asking.forEach((uid) => wanted.delete(uid));
        askingIds.forEach((id) => wantedIds.delete(id));
        const draft = clone(state);
        clearPrivate(draft, everything ? undefined : uids);
        viewers.forEach((viewer) => mergeViewer(draft, viewer));
        set(draft);
        emit('comments:viewer-loaded', { viewer: draft.viewer });
    }
    function report(uid, input, writeOptions) {
        return queue(`report:${uid}`, signature([input, writeOptions?.operationId]), 'report', async () => {
            const response = await client.report({ uid, ...input }, writeOptions).catch(refused('report', uid));
            set(applyPatch(response.comment));
            emit('comments:report-changed', { commentUid: uid, commentId: idOf(state.comments[uid]), reported: !input.withdraw });
            return response;
        });
    }
    /**
     * After `forbidden` or `blocked`, which are never retried, the action is shown as unavailable
     * until the viewer is read again. `blocked` means the person is blocked from the discussion, so
     * commenting, voting, reporting and following all go.
     */
    function refused(action, uid) {
        return (error) => {
            const code = error instanceof CommentsRequestError ? error.code : '';
            if (code === 'forbidden' || code === 'blocked') {
                const draft = clone(state);
                const blocked = code === 'blocked';
                if (uid && draft.permissions[uid]) {
                    const permissions = { ...draft.permissions[uid] };
                    if (action === 'vote' || blocked)
                        Object.assign(permissions, { canVote: false, canDownvote: false });
                    if (action === 'report' || blocked)
                        permissions.canReport = false;
                    if (blocked)
                        permissions.canReply = false;
                    draft.permissions[uid] = permissions;
                }
                if (draft.viewer && (action === 'follow' || blocked)) {
                    draft.viewer = { ...draft.viewer, follow: { ...draft.viewer.follow, available: false } };
                }
                if (draft.viewer && blocked) {
                    draft.viewer = { ...draft.viewer, blocked: true, canComment: false, canVote: false, canReport: false };
                }
                set(draft);
            }
            throw error;
        };
    }
    function setReplies(uid, patch) {
        const draft = clone(state);
        Object.assign(repliesOf(draft, uid), patch);
        set(draft);
    }
    return store;
}
/** A copy deep enough that a change to a draft never reaches the state a listener already has. */
function clone(state) {
    return {
        ...state,
        comments: { ...state.comments },
        roots: [...state.roots],
        replies: Object.fromEntries(Object.entries(state.replies).map(([uid, replies]) => [uid, { ...replies, uids: [...replies.uids] }])),
        permissions: { ...state.permissions },
    };
}
function repliesOf(draft, uid) {
    draft.replies[uid] ??= { uids: [], count: draft.comments[uid]?.replies.count ?? 0, next: draft.comments[uid]?.replies.next ?? null, loading: false };
    return draft.replies[uid];
}
/**
 * Stores a comment and its nested reply previews by UID, moving `viewer` into `permissions`. A
 * comment seen again keeps the replies already loaded for it.
 */
function ingest(draft, comment) {
    const { viewer, replies, ...rest } = comment;
    const existing = draft.replies[comment.uid];
    draft.comments[comment.uid] = { ...rest, replies: { count: replies.count, items: [], next: replies.next } };
    if (viewer) {
        draft.permissions[comment.uid] = viewer;
    }
    if (replies.count > 0 || replies.items.length > 0 || existing) {
        const entry = repliesOf(draft, comment.uid);
        entry.count = replies.count;
        if (!existing)
            entry.next = replies.next;
        for (const item of replies.items) {
            if (!entry.uids.includes(item.uid))
                entry.uids.push(item.uid);
            ingest(draft, item);
        }
    }
}
/** Refused before asking, for an action the sign-in never allows. */
function notAllowed() {
    return CommentsRequestError.from('forbidden', 'You can’t do that here.', { detail: 'not available with this sign-in' });
}
/** What makes two calls the same action, for a double click. */
function signature(parts) {
    return JSON.stringify(parts) ?? '';
}
/** Every UID in a comment and its nested previews. */
function treeUids(comment) {
    return [comment.uid, ...comment.replies.items.flatMap(treeUids)];
}
function compareDates(a, b) {
    // A placeholder has no date, and sorts as the oldest.
    return (Date.parse(a.createdAt ?? '') || 0) - (Date.parse(b.createdAt ?? '') || 0);
}
/** Puts a comment from `extraComments` among the roots where the sort would have. */
function insertSorted(roots, comment, draft) {
    const score = (item) => (item.interaction?.upvotes ?? 0) - (item.interaction?.downvotes ?? 0);
    const before = (other) => {
        if (draft.sort === 'oldest')
            return compareDates(comment, other) < 0;
        if (draft.sort === 'top')
            return score(comment) > score(other) || (score(comment) === score(other) && compareDates(comment, other) > 0);
        return compareDates(comment, other) > 0;
    };
    // A placeholder has no date or score to compare, so it's passed over.
    const index = roots.findIndex((uid) => draft.comments[uid]?.createdAt && before(draft.comments[uid]));
    roots.splice(index === -1 ? roots.length : index, 0, comment.uid);
}
