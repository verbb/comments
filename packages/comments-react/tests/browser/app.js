// The app the browser checks run: rendered to HTML in Node, then hydrated in the browser from the
// same module. A component part override reads the app's own context, a field input override
// sets a rating, and the post switches with a key.
import { createContext, createElement as e, useContext, useEffect, useState } from 'react';
import { CommentsProvider, CommentsThread } from '@verbb/comments-react';

const Tone = createContext('plain');
const record = (...entry) => globalThis.__checks?.push(entry);

function Author({ comment }) {
    const tone = useContext(Tone);

    useEffect(() => {
        record('author-mounted', comment.uid);
        return () => record('author-unmounted', comment.uid);
    }, [comment.uid]);

    return e('b', { className: 'custom-author', 'data-uid': comment.uid, 'data-tone': tone }, `${comment.author.name} (${comment.interaction.upvotes})`);
}

function Stars({ value, setValue, error, id, labelId }) {
    return e('div', { id, role: 'radiogroup', 'aria-labelledby': labelId, 'aria-invalid': error ? 'true' : undefined, className: 'stars' },
        [1, 2, 3, 4, 5].map((n) => e('button', { key: n, type: 'button', 'data-star': n, 'aria-pressed': Number(value) >= n, onClick: () => setValue(n) }, '★')));
}

export function App({ endpoint, first = 'blog/first' }) {
    const [uri, setUri] = useState(first);

    return e(Tone.Provider, { value: 'warm' },
        e(CommentsProvider, { endpoint },
            e('button', { id: 'next-post', type: 'button', onClick: () => setUri('blog/second') }, 'Next post'),
            e(CommentsThread, {
                key: uri,
                ownerUri: uri,
                loadMode: 'eager',
                count: 3,
                parts: { authorName: Author },
                fields: { rating: { input: Stars } },
                onMounted: (event) => {
                    globalThis.__controller = event.detail.controller;
                    record('mounted', event.detail.instanceId);
                },
                onCommentCreated: (event) => record('created', event.detail.commentUid),
                onDestroyed: () => record('destroyed'),
            })));
}
