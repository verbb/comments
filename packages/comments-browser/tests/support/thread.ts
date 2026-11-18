import { createThreadStore, type CommentData, type CommentViewerData, type CommentsClient, type FormFieldData, type ThreadResponse, type ThreadState, type ViewerData, type ViewerResponse } from '@verbb/comments-core';

/**
 * Builds a thread's state the way the browser gets it: synthetic `thread` and `viewer` answers
 * through core's real store, so the parts are tested against the state they'll really draw.
 */

export const LABELS: Record<string, string> = {
    commentCountOne: '1 comment', commentCountOther: '{count} comments', showComments: 'Show comments', showCommentsCount: 'Show comments ({count})',
    loading: 'Loading comments…', loadMore: 'Load more comments', empty: 'No comments yet.', beFirst: 'Be the first to comment.', closed: 'Comments are closed.',
    sortLabel: 'Sort comments', sortNewest: 'Newest', sortOldest: 'Oldest', sortTop: 'Top', reply: 'Reply', replyTo: 'Reply to {name}…', replyingTo: 'Replying to {name}',
    cancelReply: 'Cancel reply', cancel: 'Cancel', post: 'Post', posting: 'Posting…', save: 'Save comment', edit: 'Edit', edited: 'Edited', delete: 'Delete',
    deleteConfirm: 'Delete this comment? This can’t be undone.', like: 'Like', likes: 'Likes', upvote: 'Upvote', downvote: 'Downvote', score: 'Score', report: 'Report…',
    reportTitle: 'Report this comment', reportReason: 'What’s wrong with it?', reportDetails: 'Anything else?', reportDetailsHint: 'Optional. Only moderators see this.',
    sendReport: 'Send report', reportSent: 'Thanks for reporting this. A moderator will take a look.', close: 'Close', moreActions: 'More actions', copyLink: 'Copy link',
    linkCopied: 'Link copied.', showReplies: 'Show replies', hideReplies: 'Hide replies', loadMoreReplies: 'Load more replies', guest: 'Guest', commentBy: 'Comment by {name}',
    unavailable: 'Comment unavailable.', awaitingApproval: 'Waiting for approval. Only you can see this until a moderator approves it.', editorPlaceholder: 'Add to the conversation…',
    editPlaceholder: 'Edit your comment…', yourComment: 'Your comment', writeSomethingFirst: 'Write something first.', tooLong: 'Too long by {count} characters',
    charactersLeft: '{count} characters left', characterCount: '{count} of {limit} characters', draftSaved: 'Draft saved', draftRestored: 'Restored your draft from {time}.',
    discardConfirm: 'Discard what you’ve written?', discard: 'Discard', uploadInProgress: 'Please wait for your upload to finish.', joinTitle: 'Join the conversation',
    signIn: 'Sign in', signInToComment: 'Sign in to comment.', signInOrGuest: 'Sign in to comment and hear about replies, or post as a guest.', commentAsGuest: 'Comment as a guest',
    postingAs: 'Posting as {name}', yourDetails: 'Your details', yourName: 'Your name', yourEmail: 'Your email', notShownPublicly: 'Not shown publicly',
    replyEmails: 'Email me when someone replies. We’ll ask you to confirm your address first.', follow: 'Follow', following: 'Following', followByEmail: 'Follow by email',
    error: 'Something went wrong. Please try again.', tryAgain: 'Try again', moderator: 'Moderator', moderatorView: 'Moderator view.', reviewInComments: 'Review in Comments',
    heldCount: '{count} held', reportedCount: '{count} reported', showHeld: 'Show held', hideHeld: 'Hide held', approve: 'Approve', markSpam: 'Mark as spam', notSpam: 'Not spam',
    remove: 'Remove', restore: 'Restore', keep: 'Keep', spam: 'Spam', gif: 'GIF',
};

let sequence = 1400;

export function comment(overrides: Partial<CommentData> & { uid: string }): CommentData {
    const id = ++sequence;

    return {
        parentUid: null,
        depth: 0,
        state: 'approved',
        content: { format: 'plain', html: null, text: `Comment ${overrides.uid}`, gifUrl: null, gifWidth: null, gifHeight: null },
        author: { name: 'Alex Morgan', initials: 'AM', avatarUrl: null, avatarTone: 3, isGuest: false, badges: [], score: null },
        createdAt: '2026-11-09T21:04:00+00:00',
        editedAt: null,
        display: { timeAgo: '2 hours ago', fullDate: '9 November 2026, 9:04 pm' },
        permalink: `https://example.com/blog/post#comment-${id}`,
        fields: [],
        interaction: { upvotes: 0, downvotes: 0 }, folded: null,
        custom: {},
        replies: { count: 0, items: [], next: null },
        ...overrides,
    };
}

export function permissions(overrides: Partial<CommentViewerData> = {}): CommentViewerData {
    return { canReply: true, canEdit: false, canDelete: false, canVote: true, canDownvote: false, canReport: true, vote: null, reported: false, isAuthor: false, moderation: null, custom: {}, ...overrides };
}

export const MEMBER: ViewerData = {
    isGuest: false, hasSession: true, name: 'Aroha Ngata', initials: 'AN', avatarUrl: null, avatarTone: 6, badges: [], canComment: true, canVote: true, canReport: true,
    isModerator: false, blocked: false, loginUrl: null, follow: { available: true, following: false, needsEmail: false },
};

export const GUEST: ViewerData = {
    isGuest: true, hasSession: false, name: null, initials: '', avatarUrl: null, avatarTone: 0, badges: [], canComment: false, canVote: false, canReport: false,
    isModerator: false, blocked: false, loginUrl: '/login', follow: { available: false, following: false, needsEmail: true },
};

export type ThreadOptions = {
    comments?: CommentData[];
    viewer?: ViewerData | null;
    permissions?: Record<string, CommentViewerData>;
    extraComments?: CommentData[];
    conversation?: Partial<ThreadResponse['conversation']>;
    editor?: Partial<ThreadResponse['editor']>;
    fields?: { comment?: FormFieldData[]; reply?: FormFieldData[] };
    guest?: Partial<ViewerResponse['editor']['guest']>;
    gifs?: ViewerResponse['editor']['gifs'];
    moderation?: ViewerResponse['moderation'];
    next?: string | null;
};

/** A loaded thread's state, drawn from synthetic answers by core's own store. */
export async function threadState(options: ThreadOptions = {}): Promise<ThreadState> {
    const comments = options.comments ?? [comment({ uid: 'c1' }), comment({ uid: 'c2', author: { name: 'Pete from Twizel', initials: 'PF', avatarUrl: null, avatarTone: 7, isGuest: true, badges: [], score: null } })];
    const thread: ThreadResponse = {
        apiVersion: 1,
        status: 'ok',
        threadVersion: 'v1',
        conversation: {
            uid: 'conversation-1', ownerId: 109, siteId: 1, status: 'open', closedNotice: null, commentCount: comments.length, rootCount: comments.length,
            sort: 'newest', sorts: ['newest', 'oldest', 'top'], interactionMode: 'like', maxLength: null,
            guestPolicy: { allowed: false, nameAndEmail: 'required', canVote: false, canReport: false }, followAvailable: true,
            reportReasons: [{ value: 'spam', label: 'Spam', description: 'Ads, scams or links that don’t belong' }, { value: 'other', label: 'Other', description: null }],
            ...options.conversation,
        },
        editor: { authoring: 'plain', maxLength: null, richEditor: null, ...options.editor },
        comments,
        page: { next: options.next ?? null, limit: 20 },
        labels: LABELS,
        locale: 'en',
    };
    const viewer: ViewerResponse | null = options.viewer === null ? null : {
        apiVersion: 1,
        status: 'ok',
        threadVersion: 'v1',
        viewer: options.viewer ?? MEMBER,
        editor: {
            draftKey: 'comments-109-1-7',
            guest: { show: false, required: true, name: null, email: null, offerReplyEmails: false, ...options.guest },
            fields: { comment: options.fields?.comment ?? [], reply: options.fields?.reply ?? [] },
            protection: null,
            richEditor: null,
            gifs: options.gifs ?? null,
        },
        comments: options.permissions ?? Object.fromEntries(allUids(comments).map((uid) => [uid, permissions()])),
        extraComments: options.extraComments ?? [],
        moderation: options.moderation ?? null,
        csrf: null,
        custom: {},
    };
    const client = {
        siteId: 1,
        auth: { profile: 'same-origin-browser', credentials: 'same-origin', headers: async () => ({}) },
        thread: async () => thread,
        viewer: async () => viewer!,
    } as unknown as CommentsClient;
    const store = createThreadStore(client, { ownerId: 109, siteId: 1, viewer: viewer !== null });

    await store.load();
    const state = store.getState();
    store.destroy();
    return state;
}

function allUids(comments: CommentData[]): string[] {
    return comments.flatMap((item) => [item.uid, ...allUids(item.replies.items)]);
}
