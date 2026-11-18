// Compiled by scripts/check-package-pack.mjs against the packed tarball, under bundler and Node
// resolution, to prove the published types resolve and read the way the docs use them.
import { createApp, defineComponent, h, type PropType } from 'vue';
import {
    CommentsPlugin,
    CommentsThread,
    templatePart,
    useCommentForm,
    useCommentsEvent,
    useCommentsThread,
    type CommentsEventMap,
} from '@verbb/comments-vue';
import { fetchCommentsCount, fetchStructuredData, jsonLdScript } from '@verbb/comments-vue/server';
import { guestSession } from '@verbb/comments-core';

const Badge = defineComponent({
    props: { comment: { type: Object as PropType<{ author: { name: string } | null }>, required: true } },
    setup: (props) => () => h('span', { class: 'badge' }, props.comment.author?.name),
});

const Page = defineComponent({
    setup() {
        const thread = useCommentsThread(() => ({ ownerId: 12, sort: 'top' }));
        const form = useCommentForm({ thread, mode: 'reply', parentUid: () => thread.roots.value[0]?.uid ?? null });

        useCommentsEvent('comments:comment-created', (event) => console.log(event.detail.instanceId));
        useCommentsEvent('comments:comment-created', (detail) => console.log(detail.apiVersion), { thread });

        return () => [
            h(CommentsThread, {
                ownerUri: 'blog/hello',
                count: 3,
                loadMode: 'button',
                parts: { badge: Badge, meta: templatePart(({ html, defaultRender }) => html`<div class="meta">${defaultRender()}</div>`), avatar: 'acme-avatar' },
                onCommentCreated: (event: CommentsEventMap['comments:comment-created']) => console.log(event.detail.commentUid),
            }),
            h('form', { ref: form.ref, onSubmit: form.handleSubmit }, [form.fieldErrors.value.content?.[0], form.posting.value ? '…' : null]),
        ];
    },
});

createApp(Page).use(CommentsPlugin, {
    endpoint: 'https://cms.example.com/actions/comments/api',
    auth: guestSession(),
    themeConfig: { primaryButton: { resetClass: true, attributes: { class: 'btn btn-primary' } } },
});

const count: Promise<number> = fetchCommentsCount({ endpoint: 'https://cms.example.com/actions/comments/api', ownerUri: 'blog/hello' });
const data: Promise<Record<string, unknown>> = fetchStructuredData({ endpoint: 'https://cms.example.com/actions/comments/api', ownerId: 12 });

const script: Promise<string> = data.then(jsonLdScript);

void [count, script];
