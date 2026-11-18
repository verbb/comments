// The app the browser checks run: rendered to HTML in Node, then hydrated in the browser from the
// same module. A component part override reads the app's own provide, a field input override sets
// a rating, and the post switches with a key.
import { defineComponent, h, inject, onMounted, onUnmounted, ref } from 'vue';
import { CommentsThread } from '@verbb/comments-vue';

const record = (...entry) => globalThis.__checks?.push(entry);

const Author = defineComponent({
    props: ['comment'],
    setup(props) {
        const tone = inject('tone', 'plain');
        const uid = props.comment.uid;

        onMounted(() => record('author-mounted', uid));
        onUnmounted(() => record('author-unmounted', uid));

        return () => h('b', { class: 'custom-author', 'data-uid': props.comment.uid, 'data-tone': tone }, `${props.comment.author.name} (${props.comment.interaction.upvotes})`);
    },
});

const Stars = defineComponent({
    props: ['value', 'setValue', 'error', 'id', 'labelId'],
    setup(props) {
        return () => h('div', { id: props.id, role: 'radiogroup', 'aria-labelledby': props.labelId, 'aria-invalid': props.error ? 'true' : undefined, class: 'stars' },
            [1, 2, 3, 4, 5].map((n) => h('button', { key: n, type: 'button', 'data-star': n, 'aria-pressed': String(Number(props.value) >= n), onClick: () => props.setValue(n) }, '★')));
    },
});

export const App = defineComponent({
    setup() {
        const uri = ref('blog/first');

        return () => [
            h('button', { id: 'next-post', type: 'button', onClick: () => { uri.value = 'blog/second'; } }, 'Next post'),
            h(CommentsThread, {
                key: uri.value,
                ownerUri: uri.value,
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
            }),
        ];
    },
});
