// Compiled by scripts/check-package-pack.mjs against the packed tarball, under bundler and Node
// resolution, to prove the published types resolve and read the way the docs use them.
import { createElement, type ReactNode } from 'react';
import {
    CommentsProvider,
    CommentsThread,
    templatePart,
    useCommentForm,
    useCommentsEvent,
    useCommentsThread,
    type CommentsFieldInputProps,
    type CommentsPartProps,
} from '@verbb/comments-react';
import { fetchCommentsCount, fetchStructuredData, jsonLdScript } from '@verbb/comments-react/server';
import { memberToken } from '@verbb/comments-core';

function Badge({ comment, labels }: CommentsPartProps): ReactNode {
    return createElement('span', { className: 'badge', title: labels.reply }, comment?.author?.name);
}

function Stars({ value, setValue, id, labelId }: CommentsFieldInputProps): ReactNode {
    return createElement('button', { id, 'aria-labelledby': labelId, type: 'button', onClick: () => setValue(Number(value) + 1) }, '★');
}

export function Page({ count }: { count: number }): ReactNode {
    return createElement(CommentsProvider, {
        endpoint: 'https://cms.example.com/actions/comments/api',
        auth: memberToken({ getToken: () => fetch('/api/comments-token').then((response) => response.json()) }),
        themeConfig: { primaryButton: { resetClass: true, attributes: { class: 'btn btn-primary' } } },
        children: createElement(CommentsThread, {
            ownerUri: 'blog/hello',
            count,
            loadMode: 'button',
            parts: { badge: Badge, meta: templatePart(({ html, defaultRender }) => html`<div class="meta">${defaultRender()}</div>`), avatar: 'acme-avatar' },
            fields: { rating: { input: Stars } },
            onCommentCreated: (event) => console.log(event.detail.commentUid, event.detail.moderationState),
            onBeforeSubmit: (event) => {
                if (!event.detail.formData.get('content')) event.preventDefault();
            },
        }),
    });
}

export function Custom(): ReactNode {
    const thread = useCommentsThread({ ownerId: 12, sort: 'top' });
    const form = useCommentForm({ thread, mode: 'reply', parentUid: thread.roots[0]?.uid ?? null });

    useCommentsEvent('comments:comment-created', (event) => console.log(event.detail.instanceId));
    useCommentsEvent('comments:comment-created', (detail) => console.log(detail.apiVersion), { thread });

    return createElement('form', { ref: form.ref, onSubmit: form.handleSubmit }, form.fieldErrors.content?.[0], thread.roots.map((comment) => comment.uid).join(), form.posting ? '…' : null);
}

const count: Promise<number> = fetchCommentsCount({ endpoint: 'https://cms.example.com/actions/comments/api', ownerUri: 'blog/hello' });
const data: Promise<Record<string, unknown>> = fetchStructuredData({ endpoint: 'https://cms.example.com/actions/comments/api', ownerId: 12 });

const script: Promise<string> = data.then(jsonLdScript);

void [count, script];
