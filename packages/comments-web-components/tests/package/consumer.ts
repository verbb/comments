// Compiled by scripts/check-package-pack.mjs against the packed tarball, under bundler and Node
// resolution, to prove the published types resolve and read the way the README uses them.
import {
    COMMENTS_THREAD_TAG,
    CommentsThreadElement,
    registerCommentsWebComponents,
    type CommentsController,
    type CommentsEventMap,
    type PartContext,
    type ThemeConfig,
} from '@verbb/comments-web-components';

registerCommentsWebComponents();

const thread: CommentsThreadElement = document.createElement('verbb-comments');
thread.ownerUri = 'blog/hello';
thread.siteId = 1;
thread.loadMode = 'button';
thread.theme = 'auto';
thread.sort = 'top';

const themeConfig: ThemeConfig = { primaryButton: { resetClass: true, attributes: { class: 'btn btn-primary' } } };
thread.themeConfig = themeConfig;
thread.parts = { badge: ({ html, badge }: PartContext) => html`<span class="badge">${(badge as { label: string }).label}</span>`, authorName: 'acme-comment-author' };
thread.fields = { rating: { input: 'acme-stars-input', display: { component: 'Stars' } } };
thread.labels = { post: 'Send' };
thread.loadEditor = () => Promise.resolve();

thread.addEventListener('comments:comment-created', (event: CommentsEventMap['comments:comment-created']) => {
    const controller: CommentsController | undefined = event.detail.controller;
    void controller?.refresh();
});

document.body.append(thread);
const controller: CommentsController | null = thread.controller;
const tag: string = COMMENTS_THREAD_TAG;

void [controller?.load(), controller?.getHosts().length, tag, document.querySelector('verbb-comments')?.controller];
