import { isCommentsRequestError } from '@verbb/comments-core';
/**
 * A link's comment, looked up once for the page however many threads it has: the public answer,
 * or null when it isn't public (perhaps the visitor's own waiting comment, which only the right
 * thread's `viewer` read can find).
 */
const lookups = new Map();
export function lookUpComment(client, ref) {
    const key = `${client.endpoint}|${'uid' in ref ? ref.uid : ref.id}`;
    let pending = lookups.get(key);
    if (!pending) {
        pending = client.comment(ref).catch((error) => {
            if (isCommentsRequestError(error) && error.code === 'not_found')
                return null;
            lookups.delete(key);
            throw error;
        });
        lookups.set(key, pending);
    }
    return pending;
}
/** A permalink without its `#comment-N`, which is the owner's own address. */
export function permalinkBase(permalink) {
    return permalink ? permalink.split('#')[0] ?? null : null;
}
