// Compiled by scripts/check-package-pack.mjs against the packed tarball, under bundler and Node
// resolution, to prove the published types resolve and read the way the docs use them.
import {
    Comments,
    CONTROL_NAMES,
    configure,
    mountThread,
    startComments,
    type CommentsController,
    type CommentsEventMap,
    type CommentsSettings,
    type ComponentHost,
    type FieldInputContext,
    type PartContext,
} from '@verbb/comments-browser';
import { mountThread as mountNow } from '@verbb/comments-browser/renderer';

const settings: CommentsSettings = {
    theme: 'auto',
    darkSelector: 'html.dark',
    loadMode: 'button',
    themeConfig: { primaryButton: { resetClass: true, attributes: { class: 'btn btn-primary' } } },
    parts: {
        badge: ({ badge, html, defaultRender }: PartContext) => ((badge as { type: string }).type === 'verified' ? html`<span class="badge">✓</span>` : defaultRender()),
        authorName: 'acme-comment-author',
    },
    loadEditor: () => Promise.resolve(),
};

configure(settings);
const app = startComments({ observe: true });
// The renderer loads when a thread is about to be needed, so mounting from the main entry waits for it.
const controllers: Promise<CommentsController[]> = app.mount(document);
const one: CommentsController = await mountThread(document.createElement('section'), { ownerUri: 'blog/post', sort: 'top' });
// Adapters that mount threads themselves import the renderer directly, and get a controller at once.
const now: CommentsController = mountNow(document.createElement('section'), { ownerId: 12 });

// What a framework adapter does: components drawn into the hosts the renderer reports.
const adapted = mountNow(document.createElement('section'), {
    ownerUri: 'blog/post',
    parts: { badge: { component: 'VerifiedBadge' } },
    fields: { rating: { input: { component: 'Stars' } } },
    onHosts: (hosts: readonly ComponentHost[], controller: CommentsController) => void [hosts.length, controller.instanceId],
});
const unsubscribe: () => void = adapted.subscribeHosts((hosts) => {
    for (const host of hosts) {
        if (host.kind === 'input') (host.context as FieldInputContext).setValue(5);
        host.context.renderDefaultInto(host.element);
    }
});
adapted.setOptions({ parts: { authorName: { component: 'Author' } }, themeConfig: { quietButton: { attributes: { class: 'btn-ghost' } } } });
const hosts: readonly ComponentHost[] = adapted.getHosts();
unsubscribe();

document.addEventListener('comments:before-submit', (event: CommentsEventMap['comments:before-submit']) => {
    if (!event.detail.formData.get('content')) {
        event.preventDefault();
        event.detail.controller?.showFormErrors(event.detail.form, { fieldErrors: { content: ['Say something.'] } });
    }
});

document.addEventListener('comments:vote-changed', (event) => void event.detail.vote);
const names: readonly string[] = CONTROL_NAMES;

void [Comments.get(document.body), controllers, one.sort, now.instanceId, names, hosts];
