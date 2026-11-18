import { isCommentsRequestError, type CommentResponse, type CommentsClient } from '@verbb/comments-core';

/**
 * A link's comment, looked up once for the page however many threads it has: the public answer,
 * or null when it isn't public (perhaps the visitor's own waiting comment, which only the right
 * thread's `viewer` read can find).
 */
const lookups = new Map<string, Promise<CommentResponse | null>>();

export function lookUpComment(client: CommentsClient, ref: { uid: string } | { id: number }): Promise<CommentResponse | null> {
    const key = `${client.endpoint}|${'uid' in ref ? ref.uid : ref.id}`;
    let pending = lookups.get(key);

    if (!pending) {
        pending = client.comment(ref).catch((error: unknown) => {
            if (isCommentsRequestError(error) && error.code === 'not_found') return null;
            lookups.delete(key);
            throw error;
        });
        lookups.set(key, pending);
    }

    return pending;
}

/** A permalink without its `#comment-N`, which is the owner's own address. */
export function permalinkBase(permalink: string | null | undefined): string | null {
    return permalink ? permalink.split('#')[0] ?? null : null;
}
