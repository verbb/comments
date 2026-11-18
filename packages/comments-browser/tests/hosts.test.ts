// @vitest-environment happy-dom
import type { CommentData, FormFieldData, ViewerData } from '@verbb/comments-core';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { createStringView, renderToString, threadTemplate } from '../src/render';
import { destroyThreads, getThread, mountThread } from '../src/renderer';
import type { CommentsController, ComponentHost, FieldInputContext, MountOptions } from '../src/index';
import { scriptAllowed } from '../src/trusted';
import { comment, GUEST, LABELS, MEMBER, permissions, threadState } from './support/thread';

/**
 * Component hosts, which the React and Vue adapters render into: drawn by a live thread in
 * happy-dom against a stubbed data API, with the reports an adapter would get.
 */

const ENDPOINT = 'https://comments.test/actions/comments/api';
const RATING: FormFieldData = { handle: 'rating', uid: 'f-1', label: 'Rating', instructions: null, required: true, type: 'number', multiple: false, options: [], attributes: {}, public: true, placement: 'details' };

type Api = { comments: CommentData[]; fields?: FormFieldData[]; viewer?: ViewerData; requests: Array<{ action: string; body: Record<string, any> | null }>; refuse?: (action: string) => Response | null };

function api(options: Partial<Api> = {}): Api & { fetch: typeof fetch } {
    const state: Api = { comments: options.comments ?? [comment({ uid: 'c1', author: { name: 'Ana', initials: 'A', avatarUrl: null, avatarTone: 1, isGuest: false, badges: [{ type: 'verified', label: 'Verified' }], score: null } }), comment({ uid: 'c2' })], fields: options.fields ?? [], viewer: options.viewer, requests: [], refuse: options.refuse };
    const json = (body: unknown, status = 200) => new Response(JSON.stringify({ apiVersion: 1, status: status === 200 ? 'ok' : 'error', ...body as object }), { status, headers: { 'Content-Type': 'application/json', 'X-Comments-Api-Version': '1' } });

    const fetcher = (async (input: RequestInfo | URL, init?: RequestInit) => {
        const url = new URL(String(input instanceof Request ? input.url : input));
        const action = url.pathname.split('/').pop()!;
        const body = typeof init?.body === 'string' ? JSON.parse(init.body) : null;
        state.requests.push({ action, body });

        const refused = state.refuse?.(action);
        if (refused) return refused;

        if (action === 'thread') {
            return json({
                threadVersion: 'v1',
                conversation: { uid: 'conversation-1', ownerId: 109, siteId: 1, status: 'open', closedNotice: null, commentCount: state.comments.length, rootCount: state.comments.length, sort: 'newest', sorts: ['newest', 'oldest', 'top'], interactionMode: 'like', maxLength: null, guestPolicy: { allowed: false, nameAndEmail: 'required', canVote: false, canReport: false }, followAvailable: true, reportReasons: [] },
                editor: { authoring: 'plain', maxLength: null, richEditor: null },
                comments: state.comments,
                page: { next: null, limit: 20 },
                labels: LABELS,
                locale: 'en',
            });
        }

        if (action === 'viewer') {
            return json({
                threadVersion: 'v1', viewer: state.viewer ?? MEMBER,
                editor: { draftKey: 'comments-109-1-7', guest: { show: Boolean(state.viewer?.isGuest), required: true, name: null, email: null, offerReplyEmails: false }, fields: { comment: state.fields, reply: [] }, protection: null, richEditor: null },
                comments: Object.fromEntries(state.comments.map((item) => [item.uid, permissions()])), extraComments: [], moderation: null, csrf: { name: 'CRAFT_CSRF_TOKEN', value: 'token' }, custom: {},
            });
        }

        if (action === 'protection') {
            return json({ protection: null, csrf: { name: 'CRAFT_CSRF_TOKEN', value: 'token' } });
        }

        if (action === 'vote') {
            return json({ replayed: false, comment: { uid: body.uid, interaction: { upvotes: 3, downvotes: 0 }, viewer: permissions({ vote: body.vote }) }, announcement: null });
        }

        if (action === 'delete') {
            return json({ replayed: false, uid: body.uid, conversation: { commentCount: state.comments.length - 1 }, announcement: 'Comment deleted.' });
        }

        if (action === 'create') {
            return json({ replayed: false, comment: { ...comment({ uid: 'new-1', content: { format: 'plain', html: null, text: body.content.text, gifUrl: null, gifWidth: null, gifHeight: null } }), viewer: permissions({ isAuthor: true }) }, moderationState: 'approved', conversation: { commentCount: 3 }, announcement: 'Comment posted.' });
        }

        return json({}, 404);
    }) as typeof fetch;

    return Object.assign(state, { fetch: fetcher });
}

let mounted: CommentsController[] = [];

// The server's mount point holds a heading, which a morph puts back.
const HEADING = '<h2 class="comments__heading">2 comments</h2>';
const heading = () => Object.assign(document.createElement('h2'), { className: 'comments__heading', textContent: '2 comments' });

function mount(server: ReturnType<typeof api>, options: MountOptions = {}): CommentsController {
    const root = document.createElement('section');
    root.innerHTML = HEADING;
    document.body.append(root);
    const controller = mountThread(root, { ownerId: 109, siteId: 1, endpoint: ENDPOINT, loadMode: 'eager', fetch: server.fetch, ...options });
    mounted.push(controller);
    return controller;
}

/** Waits for the thread to draw, its forms to arrive and every report to be made. */
async function settle(until: () => boolean = () => true): Promise<void> {
    for (let i = 0; i < 100; i++) {
        await new Promise((resolve) => setTimeout(resolve, 10));
        if (until()) return;
    }

    throw new Error('Timed out waiting for the thread.');
}

const ready = (controller: CommentsController) => controller.root.dataset.commentsPhase === 'ready';
const hostByKey = (controller: CommentsController, key: string) => controller.getHosts().find((host) => host.key === key);

afterEach(() => {
    destroyThreads(document);
    mounted = [];
    document.body.replaceChildren();
});

describe('component hosts', () => {
    it('draws an empty, stable host for a part, keyed by comment, and keeps it through a redraw', async () => {
        const server = api();
        const reports: Array<readonly ComponentHost[]> = [];
        const controller = mount(server, { parts: { badge: { component: 'VerifiedBadge' }, actions: { component: 'Actions' } } });
        controller.subscribeHosts((hosts) => reports.push(hosts));
        await settle(() => ready(controller) && controller.getHosts().length === 3);

        const badge = hostByKey(controller, 'comment:c1:badge:0')!;
        expect(controller.getHosts().map((host) => host.key).sort()).toEqual(['comment:c1:actions', 'comment:c1:badge:0', 'comment:c2:actions']);
        expect(badge.kind).toBe('part');
        expect(badge.name).toBe('badge');
        expect(badge.component).toBe('VerifiedBadge');
        expect(badge.element.tagName).toBe('VERBB-COMMENTS-HOST');
        expect(badge.element.dataset).toMatchObject({ commentsHost: 'comment:c1:badge:0', commentsHostKind: 'part', commentsHostName: 'badge' });
        expect(badge.element.childNodes.length).toBe(0);
        expect(controller.root.contains(badge.element)).toBe(true);
        expect((badge.context.badge as { label: string }).label).toBe('Verified');
        expect((badge.context.comment as CommentData).uid).toBe('c1');
        expect(typeof badge.context.actions.vote).toBe('function');
        // getHosts() is the last report, the same array until the next.
        expect(controller.getHosts()).toBe(reports.at(-1));
        expect(controller.getHosts()).toBe(controller.getHosts());

        // What an adapter would render, which the renderer must leave alone.
        badge.element.textContent = 'Rendered by the app';
        const actions = hostByKey(controller, 'comment:c1:actions')!;
        const before = reports.length;
        await actions.context.actions.vote!('upvote');
        await settle(() => reports.length > before);

        const after = hostByKey(controller, 'comment:c1:badge:0')!;
        expect(after.element).toBe(badge.element);
        expect(after.element.textContent).toBe('Rendered by the app');
        expect(after.element.isConnected).toBe(true);
        // The vote's new count reaches the actions component through its context.
        expect((hostByKey(controller, 'comment:c1:actions')!.context.comment as CommentData).interaction?.upvotes).toBe(3);

        // Unsubscribing stops the reports.
        const late = vi.fn();
        controller.subscribeHosts(late)();
        const count = reports.length;
        await hostByKey(controller, 'comment:c1:actions')!.context.actions.vote!(null);
        await settle(() => reports.length > count);
        expect(late).not.toHaveBeenCalled();
    });

    it('reports once per draw however many hosts it drew', async () => {
        const comments = Array.from({ length: 40 }, (_, index) => comment({ uid: `c${index}` }));
        const server = api({ comments });
        const onHosts = vi.fn();
        const controller = mount(server, { parts: { authorName: { component: 'Author' } }, onHosts });
        await settle(() => ready(controller) && controller.getHosts().length === 40);
        await settle();

        // A draw for the waiting thread, then the loaded thread's, each one report at most.
        expect(onHosts.mock.calls.length).toBeLessThanOrEqual(3);
        expect(onHosts.mock.calls.at(-1)![0]).toHaveLength(40);
        expect(onHosts.mock.calls.at(-1)![1]).toBe(controller);
    });

    it('drops the host of a comment that was removed', async () => {
        const server = api();
        const controller = mount(server, { parts: { content: { component: 'Content' } } });
        await settle(() => ready(controller) && controller.getHosts().length === 2);
        const removed = hostByKey(controller, 'comment:c2:content')!;

        window.confirm = () => true;
        await (removed.context.actions as { delete(): Promise<void> }).delete();
        await settle(() => !hostByKey(controller, 'comment:c2:content'));

        expect(controller.getHosts().map((host) => host.key)).toEqual(['comment:c1:content']);
        expect(removed.element.isConnected).toBe(false);
    });

    it('draws a component for the whole comment inside Comments’ list item, with its form and replies after it', async () => {
        const reply = comment({ uid: 'c1-1', parentUid: 'c1', depth: 1 });
        const server = api({ comments: [comment({ uid: 'c1', replies: { count: 1, items: [reply], next: null } })] });
        const controller = mount(server, { parts: { comment: { component: 'Comment' } } });
        await settle(() => ready(controller) && controller.getHosts().length === 2);

        const host = hostByKey(controller, 'comment:c1:comment')!;
        const item = host.element.closest('li[data-comments-comment]') as HTMLElement;
        expect(item.dataset.commentsCommentUid).toBe('c1');
        expect(host.element.parentElement!.tagName).toBe('ARTICLE');
        expect(host.element.parentElement!.id).toMatch(/^comment-/);
        expect(item.querySelector(':scope > article > [data-comments-region="contextual-editor"]')).not.toBeNull();
        // The reply is drawn by Comments, under the comment, through the same override.
        expect(item.querySelector('[data-comments-comment-uid="c1-1"] verbb-comments-host')).toBe(hostByKey(controller, 'comment:c1-1:comment')!.element);
        expect('form' in host.context || 'replies' in host.context).toBe(false);
    });

    it('gives a field input its value, takes what it sets, posts it and shows the error the server sends back', async () => {
        let refuse = true;
        const server = api({
            fields: [RATING],
            refuse: (action) => (action === 'create' && refuse ? new Response(JSON.stringify({ apiVersion: 1, status: 'error', errors: [{ category: 'validation', code: 'validation_failed', message: 'Check the form.', fieldErrors: { 'fields.rating': ['Pick a rating.'] }, recoverable: true, retryAfter: null }] }), { status: 422, headers: { 'Content-Type': 'application/json', 'X-Comments-Api-Version': '1' } }) : null),
        });
        const controller = mount(server, { fields: { rating: { input: { component: 'Stars' }, display: { component: 'StarsShown' } } } });
        await settle(() => Boolean(hostByKey(controller, 'form:create:input:rating')));

        const input = () => hostByKey(controller, 'form:create:input:rating')!;
        const context = () => input().context as FieldInputContext;
        expect(input().kind).toBe('input');
        expect(context().value).toBeNull();
        expect(context().error).toBeNull();
        expect(context().id).toBe(`${controller.instanceId}-create-new-rating`);
        expect(input().element.closest('[data-comments-field-override]')?.querySelector('.comments__field-label')?.textContent).toContain('Rating');

        // As a component would: draws a control with the context's ID, and sets the value.
        const element = input().element;
        const control = document.createElement('button');
        control.type = 'button';
        control.id = context().id;
        element.append(control);
        context().setValue(4);
        await settle(() => context().value === 4);
        expect(input().element).toBe(element);

        const form = controller.root.querySelector<HTMLFormElement>('form.comments__editor--create')!;
        form.querySelector<HTMLTextAreaElement>('textarea[data-comments-body]')!.value = 'Four stars.';
        form.requestSubmit();
        await settle(() => context().error === 'Pick a rating.');
        expect(server.requests.find((request) => request.action === 'create')?.body?.fields).toEqual({ rating: 4 });
        // Focus goes to the component's control, found by the context's ID.
        await settle(() => document.activeElement === control);

        // Setting a value clears the error, and the next post goes through.
        context().setValue(5);
        await settle(() => context().error === null && context().value === 5);
        refuse = false;
        form.requestSubmit();
        await settle(() => server.requests.filter((request) => request.action === 'create').length === 2);
        expect(server.requests.filter((request) => request.action === 'create')[1]!.body!.fields).toEqual({ rating: 5 });
    });

    it('replaces a thread’s own options with setOptions(), and reports none when the thread stops', async () => {
        const server = api();
        const controller = mount(server);
        const reports: Array<readonly ComponentHost[]> = [];
        controller.subscribeHosts((hosts) => reports.push(hosts));
        await settle(() => ready(controller));
        expect(controller.getHosts()).toEqual([]);

        controller.setOptions({ parts: { avatar: { component: 'Avatar' } }, themeConfig: { quietButton: { attributes: { class: 'btn-ghost' } } } });
        await settle(() => controller.getHosts().length === 2);
        expect(controller.root.querySelector('[data-comments-action="reply"]')!.classList.contains('btn-ghost')).toBe(true);

        // renderDefaultInto() draws Comments' own avatar into an element of the component's.
        const target = document.createElement('div');
        controller.getHosts()[0]!.context.renderDefaultInto(target);
        expect(target.querySelector('.comments__avatar')).not.toBeNull();

        const elements = controller.getHosts().map((host) => host.element);
        controller.destroy();
        expect(reports.at(-1)).toEqual([]);
        expect(controller.getHosts()).toEqual([]);
        expect(elements.every((element) => !element.isConnected)).toBe(true);
    });

    it('draws an empty host when drawing to a string', async () => {
        const state = await threadState({ comments: [comment({ uid: 'c1' })] });
        const html = renderToString(threadTemplate(createStringView({ state, parts: { authorName: { component: 'Author' }, comment: undefined } })));

        expect(html).toContain('<verbb-comments-host></verbb-comments-host>');
    });
});

/** Draws what an adapter's component would: Comments' default, through renderDefaultInto(), into an element of its own. */
function drawDefaults(controller: CommentsController, names: string[]): () => number {
    let reports = 0;
    controller.subscribeHosts((hosts) => {
        reports++;
        if (reports > 50) return;

        for (const host of hosts) {
            if (!names.includes(host.name)) continue;

            let into = host.element.querySelector<HTMLElement>(':scope > .mine');
            if (!into) {
                into = document.createElement('div');
                into.className = 'mine';
                host.element.append(into);
            }
            host.context.renderDefaultInto(into);
        }
    });

    return () => reports;
}

describe('component hosts after the stage F review', () => {
    it('draws only what goes inside a component comment as its default, once, with its form and replies left to Comments', async () => {
        const reply = comment({ uid: 'c1-1', parentUid: 'c1', depth: 1 });
        const server = api({ comments: [comment({ uid: 'c1', author: { name: 'Ana', initials: 'A', avatarUrl: null, avatarTone: 1, isGuest: false, badges: [{ type: 'verified', label: 'Verified' }], score: null }, replies: { count: 1, items: [reply], next: null } })] });
        const controller = mount(server, { parts: { comment: { component: 'Comment' }, badge: { component: 'Badge' } } });
        const reports = drawDefaults(controller, ['comment']);
        await settle(() => ready(controller) && Boolean(hostByKey(controller, 'comment:c1:badge:0')));
        await new Promise((resolve) => setTimeout(resolve, 100));
        const settled = reports();
        await new Promise((resolve) => setTimeout(resolve, 100));

        const root = controller.root;
        const ids = Array.from(root.querySelectorAll('[id]'), (element) => element.id);
        expect(new Set(ids).size).toBe(ids.length);
        expect(root.querySelectorAll('li[data-comments-comment-uid="c1"]')).toHaveLength(1);
        expect(root.querySelectorAll('[data-comments-region="contextual-editor"][data-comments-comment-uid="c1"]')).toHaveLength(1);
        const into = hostByKey(controller, 'comment:c1:comment')!.element.querySelector('.mine')!;
        expect(into.querySelector('.comments__author-name')?.textContent).toBe('Ana');
        expect(into.querySelector('[data-comments-comment], .comments__replies, [data-comments-region="contextual-editor"]')).toBeNull();
        // The reply, drawn by Comments after the component, isn't moved into it.
        expect(into.contains(hostByKey(controller, 'comment:c1-1:comment')!.element)).toBe(false);
        // The badge inside the default is a host of its own, reported once, and the reports stop.
        expect(into.contains(hostByKey(controller, 'comment:c1:badge:0')!.element)).toBe(true);
        expect(reports()).toBe(settled);
        expect(settled).toBeLessThan(6);
    });

    it('keeps a join component’s default current, with the guest’s one form inside it', async () => {
        const server = api({ viewer: { ...GUEST, canComment: true } });
        const controller = mount(server, { parts: { join: { component: 'Join' } } });
        drawDefaults(controller, ['join']);
        await settle(() => Boolean(controller.root.querySelector('verbb-comments-host .mine .comments__join')));

        controller.root.querySelector<HTMLElement>('.mine [data-comments-action="join-guest"]')!.click();
        await settle(() => Boolean(controller.root.querySelector('.mine form[data-comments-form="create"]')));
        expect(controller.root.querySelectorAll('form[data-comments-form="create"]')).toHaveLength(1);
    });

    it('draws a formFields component’s default with a component input inside it, without looping', async () => {
        const server = api({ fields: [RATING] });
        const controller = mount(server, { parts: { formFields: { component: 'Fields' } }, fields: { rating: { input: { component: 'Stars' } } } });
        const reports = drawDefaults(controller, ['formFields']);
        await settle(() => Boolean(hostByKey(controller, 'form:create:input:rating')));
        await new Promise((resolve) => setTimeout(resolve, 150));
        const settled = reports();
        await new Promise((resolve) => setTimeout(resolve, 150));

        const fields = hostByKey(controller, 'form:create:formFields')!.element;
        expect(fields.querySelector('.mine')!.contains(hostByKey(controller, 'form:create:input:rating')!.element)).toBe(true);
        expect(reports()).toBe(settled);
    });

    it('reports nothing for a draw that changes nothing, and only the hosts whose data changed', async () => {
        const comments = Array.from({ length: 20 }, (_, index) => comment({ uid: `c${index}` }));
        const server = api({ comments });
        const controller = mount(server, { parts: { authorName: { component: 'Author' } } });
        await settle(() => ready(controller) && controller.getHosts().length === 20);
        await new Promise((resolve) => setTimeout(resolve, 100));
        const reports: Array<readonly ComponentHost[]> = [];
        controller.subscribeHosts((hosts) => reports.push(hosts));
        const before = hostByKey(controller, 'comment:c5:authorName')!;

        // Drawn again with nothing changed for any host.
        controller.setOptions({ labels: {} });
        controller.setTheme('dark');
        (controller as unknown as { announce(message: string): void }).announce('Hello');
        await new Promise((resolve) => setTimeout(resolve, 150));
        expect(reports).toHaveLength(0);

        await hostByKey(controller, 'comment:c0:authorName')!.context.actions.vote!('upvote');
        await settle(() => reports.length > 0);
        await new Promise((resolve) => setTimeout(resolve, 150));
        expect(reports).toHaveLength(1);
        expect(hostByKey(controller, 'comment:c5:authorName')).toBe(before);
        expect(hostByKey(controller, 'comment:c0:authorName')!.context.comment).not.toBe(before.context.comment);
    });

    it('ignores setOptions() with options equal to the thread’s, even made afresh', async () => {
        const server = api();
        const controller = mount(server, { parts: { authorName: { component: 'Author' } } });
        await settle(() => ready(controller) && controller.getHosts().length === 2);
        let reports = 0;
        controller.subscribeHosts(() => {
            reports++;
            if (reports < 100) controller.setOptions({ parts: { authorName: { component: 'Author' } } });
        });
        controller.setOptions({ parts: { authorName: { component: 'Author' } }, labels: { reply: 'Answer' } });
        await new Promise((resolve) => setTimeout(resolve, 300));
        expect(reports).toBeLessThan(3);
    });

    it('keeps every host’s element when a long thread is sorted again', async () => {
        const comments = Array.from({ length: 60 }, (_, index) => comment({ uid: `c${index}` }));
        const server = api({ comments });
        const controller = mount(server, { parts: { authorName: { component: 'Author' } } });
        await settle(() => ready(controller) && controller.getHosts().length === 60);
        const before = new Map(controller.getHosts().map((host) => [host.key, host.element]));

        controller.refresh({ sort: 'oldest' });
        await settle(() => controller.sort === 'oldest' && server.requests.filter((request) => request.action === 'thread').length === 2);
        await new Promise((resolve) => setTimeout(resolve, 300));

        expect(controller.getHosts()).toHaveLength(60);
        expect(controller.getHosts().every((host) => before.get(host.key) === host.element)).toBe(true);
    });

    it('draws a thread a morph took again with the same controller, options, listeners and host elements', async () => {
        const server = api();
        const controller = mount(server, { parts: { badge: { component: 'Badge' } } });
        const reports: Array<readonly ComponentHost[]> = [];
        controller.subscribeHosts((hosts) => reports.push(hosts));
        await settle(() => ready(controller) && controller.getHosts().length === 1);
        controller.setOptions({ labels: { reply: 'Answer' } });
        await new Promise((resolve) => setTimeout(resolve, 50));
        const badge = hostByKey(controller, 'comment:c1:badge:0')!.element;
        badge.textContent = 'Rendered';

        controller.root.replaceChildren(heading());
        await settle(() => controller.root.querySelectorAll('[data-comments-comment]').length === 2);

        expect(controller.root.querySelectorAll(':scope > h2')).toHaveLength(0);
        expect(controller.root.dataset.commentsJs).toBe('1');
        expect(controller.root.querySelector('[data-comments-action="reply"]')!.textContent!.trim()).toBe('Answer');
        expect(hostByKey(controller, 'comment:c1:badge:0')!.element).toBe(badge);
        expect(badge.isConnected && badge.textContent).toBe('Rendered');
        expect(server.requests.filter((request) => request.action === 'thread')).toHaveLength(1);

        // Still the one listening: a vote reaches the same listener.
        const count = reports.length;
        controller.setOptions({ parts: { badge: { component: 'Badge' }, authorName: { component: 'Author' } } });
        await settle(() => reports.length > count);
    });

    it('stops cleanly after a morph took its contents, and draws a default into an element something else emptied', async () => {
        const server = api();
        const controller = mount(server, { parts: { avatar: { component: 'Avatar' } } });
        await settle(() => ready(controller) && controller.getHosts().length === 2);

        const host = controller.getHosts()[0]!;
        const into = document.createElement('div');
        host.element.append(into);
        host.context.renderDefaultInto(into);
        expect(into.querySelector('.comments__avatar')).not.toBeNull();
        // A framework replacing what was drawn there, which lit-html's own markers went with.
        into.replaceChildren(document.createElement('span'));
        expect(() => host.context.renderDefaultInto(into)).not.toThrow();
        expect(into.querySelector('.comments__avatar')).not.toBeNull();

        controller.root.replaceChildren();
        expect(() => controller.destroy()).not.toThrow();
    });

    it('gives two threads on one page by URI different IDs', async () => {
        // Each with its own comments, whose anchors are the comments' own.
        const first = mount(api(), { ownerId: undefined, ownerUri: 'blog/Hello World' });
        const second = mount(api(), { ownerId: undefined, ownerUri: 'blog/Hello World' });
        await settle(() => ready(first) && ready(second));

        expect(first.instanceId).toBe('comments-blog-hello-world');
        expect(second.instanceId).toBe('comments-blog-hello-world-2');
        const ids = Array.from(document.querySelectorAll('[id]'), (element) => element.id);
        expect(new Set(ids).size).toBe(ids.length);
    });

    it('trusts Editor’s module from an endpoint given in code, not one read from markup on another origin', () => {
        const server = api();
        mount(server, { endpoint: 'https://markup.example/actions/comments/api', endpointFromMarkup: true } as MountOptions);
        mount(server, { endpoint: 'https://code.example/actions/comments/api' });

        expect(scriptAllowed('https://markup.example/editor.js')).toBe(false);
        expect(scriptAllowed('https://code.example/editor.js')).toBe(true);
        expect(scriptAllowed(new URL('/editor.js', location.href).href)).toBe(true);
    });

    it('ignores actions inside a content component, as inside comment text', async () => {
        const server = api();
        const controller = mount(server, { parts: { content: { component: 'Content' } } });
        await settle(() => ready(controller) && controller.getHosts().length === 2);
        const actions = vi.fn();
        controller.root.addEventListener('comments:before-action', actions);

        const button = document.createElement('button');
        button.dataset.commentsAction = 'reply';
        button.dataset.commentsCommentUid = 'c1';
        hostByKey(controller, 'comment:c1:content')!.element.append(button);
        button.click();
        expect(actions).not.toHaveBeenCalled();
    });

    it('follows an input override given or taken away while a form is open', async () => {
        const server = api({ fields: [RATING] });
        const controller = mount(server, { fields: { rating: { input: { component: 'Stars' } } } });
        await settle(() => Boolean(hostByKey(controller, 'form:create:input:rating')));
        (hostByKey(controller, 'form:create:input:rating')!.context as FieldInputContext).setValue(4);
        await settle(() => (hostByKey(controller, 'form:create:input:rating')!.context as FieldInputContext).value === 4);

        controller.setOptions({ fields: {} });
        await settle(() => !hostByKey(controller, 'form:create:input:rating'));
        const form = controller.root.querySelector<HTMLFormElement>('form.comments__editor--create')!;
        const builtIn = form.querySelector<HTMLInputElement>('input[name="fields[rating]"]')!;
        expect(builtIn.value).toBe('4');
        builtIn.value = '2';
        form.querySelector<HTMLTextAreaElement>('textarea[data-comments-body]')!.value = 'Two.';
        form.requestSubmit();
        await settle(() => server.requests.some((request) => request.action === 'create'));
        expect(server.requests.find((request) => request.action === 'create')!.body!.fields).toEqual({ rating: '2' });
    });

    it('focuses the first control of a component group that can’t take focus itself, ahead of other invalid fields', async () => {
        const server = api({
            // Plain comes first on the form, so it's the first invalid control in it, but rating is the first error.
            fields: [{ ...RATING, handle: 'plain', uid: 'f-2', label: 'Plain', type: 'text' }, RATING],
            refuse: (action) => (action === 'create' ? new Response(JSON.stringify({ apiVersion: 1, status: 'error', errors: [{ category: 'validation', code: 'validation_failed', message: 'Check the form.', fieldErrors: { 'fields.rating': ['Pick a rating.'], 'fields.plain': ['Say something.'] }, recoverable: true, retryAfter: null }] }), { status: 422, headers: { 'Content-Type': 'application/json', 'X-Comments-Api-Version': '1' } }) : null),
        });
        const controller = mount(server, { fields: { rating: { input: { component: 'Stars' } } } });
        await settle(() => Boolean(hostByKey(controller, 'form:create:input:rating')));
        const input = hostByKey(controller, 'form:create:input:rating')!;
        // A radiogroup with no tabindex of its own, marked invalid when the context says so.
        const group = document.createElement('div');
        group.setAttribute('role', 'radiogroup');
        const star = document.createElement('button');
        star.type = 'button';
        group.append(star);
        input.element.append(group);
        controller.subscribeHosts((hosts) => {
            const error = (hosts.find((host) => host.key === 'form:create:input:rating')?.context as FieldInputContext | undefined)?.error;
            if (error) group.setAttribute('aria-invalid', 'true');
        });
        (input.context as FieldInputContext).setValue(1);

        const form = controller.root.querySelector<HTMLFormElement>('form.comments__editor--create')!;
        form.querySelector<HTMLTextAreaElement>('textarea[data-comments-body]')!.value = 'Hi.';
        form.requestSubmit();
        await settle(() => document.activeElement === star);
    });
});

describe('component hosts after the second stage F review', () => {
    it('lets go of a host whose component draws its default into its own element, once its comment goes', async () => {
        const server = api({ comments: [comment({ uid: 'c1' }), comment({ uid: 'c2' })] });
        const controller = mount(server, { parts: { content: { component: 'Content' } } });
        controller.subscribeHosts((hosts) => hosts.forEach((host) => host.context.renderDefaultInto(host.element)));
        await settle(() => ready(controller) && controller.getHosts().length === 2);
        await new Promise((resolve) => setTimeout(resolve, 50));

        window.confirm = () => true;
        await hostByKey(controller, 'comment:c1:content')!.context.actions.delete!();
        await settle(() => !hostByKey(controller, 'comment:c1:content'));
        expect(controller.getHosts().map((host) => host.key)).toEqual(['comment:c2:content']);
    });

    it('lets go of a host inside a removed component’s default in the same report', async () => {
        const badged = (uid: string) => comment({ uid, author: { name: 'Ana', initials: 'A', avatarUrl: null, avatarTone: 1, isGuest: false, badges: [{ type: 'verified', label: 'Verified' }], score: null } });
        const server = api({ comments: [badged('c1'), badged('c2')] });
        const controller = mount(server, { parts: { comment: { component: 'Comment' }, badge: { component: 'Badge' } } });
        const reports: string[][] = [];
        drawDefaults(controller, ['comment']);
        controller.subscribeHosts((hosts) => reports.push(hosts.map((host) => host.key)));
        await settle(() => ready(controller) && controller.getHosts().length === 4);
        await new Promise((resolve) => setTimeout(resolve, 50));

        window.confirm = () => true;
        const count = reports.length;
        await hostByKey(controller, 'comment:c1:comment')!.context.actions.delete!();
        await settle(() => reports.length > count && !reports.at(-1)!.includes('comment:c1:comment'));
        expect(reports.at(-1)!.sort()).toEqual(['comment:c2:badge:0', 'comment:c2:comment']);
    });

    it('draws a re-sorted long thread in batches again, keeping the hosts of comments that wait for theirs', async () => {
        const comments = Array.from({ length: 60 }, (_, index) => comment({ uid: `c${index}` }));
        const server = api({ comments });
        const controller = mount(server, { parts: { authorName: { component: 'Author' } } });
        await settle(() => ready(controller) && controller.root.querySelectorAll('[data-comments-comment]').length === 60 && controller.getHosts().length === 60);
        const before = new Map(controller.getHosts().map((host) => [host.key, host.element]));
        let firstDraw = 0;
        controller.root.addEventListener('comments:before-render', (event) => {
            if ((event as CustomEvent).detail.reason === 'thread') queueMicrotask(() => { firstDraw = controller.root.querySelectorAll('[data-comments-comment]').length; });
        });

        controller.refresh({ sort: 'oldest' });
        await settle(() => firstDraw > 0);
        expect(firstDraw).toBe(45);
        // Waiting for their batch, the others' hosts are kept, their elements out of the page for now.
        expect(controller.getHosts()).toHaveLength(60);

        await settle(() => controller.root.querySelectorAll('[data-comments-comment]').length === 60);
        await new Promise((resolve) => setTimeout(resolve, 50));
        expect(controller.getHosts().every((host) => before.get(host.key) === host.element && host.element.isConnected)).toBe(true);
    });

    it('redraws for options changed in place and given again, or copied', async () => {
        const server = api();
        const parts: Record<string, unknown> = {};
        const controller = mount(server, { parts });
        await settle(() => ready(controller));

        parts.authorName = ({ html }: { html: typeof import('lit-html').html }) => html`<b class="mutated">M</b>`;
        controller.setOptions({ parts });
        await settle(() => controller.root.querySelectorAll('.mutated').length === 2);

        parts.authorName = ({ html }: { html: typeof import('lit-html').html }) => html`<i class="again">A</i>`;
        controller.setOptions({ parts: { ...parts } });
        await settle(() => controller.root.querySelectorAll('.again').length === 2);
    });

    it('starts the other thread when a swap makes its root another owner’s mount point', async () => {
        const server = api();
        const root = document.createElement('section');
        root.dataset.commentsOwnerId = '109';
        root.dataset.commentsEndpoint = ENDPOINT;
        document.body.append(root);
        const first = mountThread(root, { loadMode: 'eager', fetch: server.fetch });
        await settle(() => ready(first));

        root.dataset.commentsOwnerId = '110';
        root.innerHTML = '<h2>2 comments</h2>';
        await settle(() => getThread(root) !== first && ready(getThread(root)!));
        expect(server.requests.filter((request) => request.action === 'thread')).toHaveLength(2);
    });

    it('notices a morph that keeps the live region but takes the rest, and gives focus back', async () => {
        const server = api();
        const controller = mount(server);
        await settle(() => ready(controller) && Boolean(controller.root.querySelector('form[data-comments-form="create"] textarea')));
        const box = controller.root.querySelector<HTMLTextAreaElement>('form[data-comments-form="create"] textarea')!;
        box.focus();
        expect(document.activeElement).toBe(box);

        // A site's note beside the thread before the swap, which a draw has seen since.
        const note = Object.assign(document.createElement('p'), { className: 'site-note', textContent: 'Be kind.' });
        controller.root.append(note);
        controller.setOptions({ labels: { reply: 'Answer' } });
        await settle(() => controller.root.querySelector('[data-comments-action="reply"]')!.textContent!.trim() === 'Answer');
        box.focus();

        // The swap keeps the live region, puts the heading back, and brings a second live region of its own.
        const live = controller.root.querySelector(':scope > [data-comments-region="live"]')!;
        controller.root.replaceChildren(live, heading(), live.cloneNode(), note);
        await settle(() => controller.root.querySelectorAll('[data-comments-comment]').length === 2);
        expect(controller.root.querySelector(':scope > h2')).toBeNull();
        expect(controller.root.querySelectorAll(':scope > [data-comments-region="live"]')).toHaveLength(1);
        // The site's note stays, after the thread.
        expect(controller.root.lastElementChild).toBe(note);
        await settle(() => document.activeElement === box);
    });

    it('leaves an element a site adds beside the thread alone, without drawing again', async () => {
        const server = api();
        const controller = mount(server);
        await settle(() => ready(controller) && controller.root.querySelectorAll('[data-comments-comment]').length === 2);
        const list = controller.root.querySelector('.comments__list');
        let added = 0;
        // A script that keeps its note in place, which must not set the two drawing against each other.
        const keeper = new MutationObserver(() => {
            if (!controller.root.querySelector(':scope > .site-note') && added++ < 50) controller.root.append(Object.assign(document.createElement('p'), { className: 'site-note' }));
        });
        keeper.observe(controller.root, { childList: true });
        controller.root.append(Object.assign(document.createElement('p'), { className: 'site-note' }));
        await new Promise((resolve) => setTimeout(resolve, 200));
        keeper.disconnect();

        expect(added).toBe(0);
        expect(controller.root.querySelector('.comments__list')).toBe(list);
        // A later draw leaves it be too.
        controller.setOptions({ labels: { reply: 'Answer' } });
        await settle(() => controller.root.querySelector('[data-comments-action="reply"]')!.textContent!.trim() === 'Answer');
        expect(controller.root.querySelector(':scope > .site-note')).not.toBeNull();
    });

    it('puts back the new owner’s markup when a thread started for a reused root stops', async () => {
        const server = api();
        const root = document.createElement('section');
        root.dataset.commentsOwnerId = '109';
        root.dataset.commentsEndpoint = ENDPOINT;
        root.innerHTML = '<h2>8 comments</h2>';
        document.body.append(root);
        const first = mountThread(root, { loadMode: 'eager', fetch: server.fetch });
        await settle(() => ready(first));

        // A site's note beside the thread, which a draw has seen.
        const note = Object.assign(document.createElement('p'), { className: 'site-note' });
        root.append(note);
        first.setOptions({ labels: { reply: 'Answer' } });
        await new Promise((resolve) => setTimeout(resolve, 50));

        // The swap keeps the live region and the note, and puts the new owner's heading in.
        root.dataset.commentsOwnerId = '110';
        Array.from(root.children).forEach((child) => { if (!child.matches('[data-comments-region="live"], .site-note')) child.remove(); });
        root.prepend(Object.assign(document.createElement('h2'), { textContent: '2 comments' }));
        await settle(() => getThread(root) !== first && ready(getThread(root)!));
        expect(root.lastElementChild).toBe(note);
        expect(root.querySelectorAll(':scope > [data-comments-region="live"]')).toHaveLength(1);
        getThread(root)!.destroy();
        // Stopping puts back the mount point as the new owner's markup, with nothing of the old thread's.
        expect(root.innerHTML).toBe('<h2>2 comments</h2>');
    });

    it('gives focus back inside a component whose comment a re-sort held for a later batch', async () => {
        const comments = Array.from({ length: 60 }, (_, index) => comment({ uid: `c${index}` }));
        const server = api({ comments });
        const controller = mount(server, { parts: { content: { component: 'Content' } } });
        controller.subscribeHosts((hosts) => hosts.forEach((host) => {
            if (!host.element.firstChild) host.element.append(Object.assign(document.createElement('input'), { className: 'draft' }));
        }));
        await settle(() => ready(controller) && controller.root.querySelectorAll('.draft').length === 60);
        const input = hostByKey(controller, 'comment:c0:content')!.element.querySelector('input')!;
        input.focus();
        expect(document.activeElement).toBe(input);

        comments.reverse();
        controller.refresh({ sort: 'oldest' });
        // c0 comes last now, so it waits for a later batch, and is focused again once it's back.
        await settle(() => controller.root.querySelectorAll('[data-comments-comment]').length === 60 && controller.root.querySelector('[data-comments-comment]')!.getAttribute('data-comments-comment-uid') === 'c59');
        await settle(() => document.activeElement === input);
    });

    it('only draws the report dialog once someone heads for it, then reports through it', async () => {
        const server = api();
        const controller = mount(server);
        await settle(() => ready(controller) && Boolean(controller.root.querySelector('.comments__menu')));

        // Reading the thread draws no dialog, so its chunk never needs to load.
        expect(controller.root.querySelector('dialog[data-comments-report-dialog]')).toBeNull();

        const menu = controller.root.querySelector<HTMLDetailsElement>('.comments__menu')!;
        menu.open = true;
        menu.dispatchEvent(new Event('toggle'));
        await settle(() => Boolean(controller.root.querySelector('dialog[data-comments-report-dialog]')));

        controller.root.querySelector<HTMLElement>('[data-comments-action="report"]')!.click();
        await settle(() => Boolean(controller.root.querySelector<HTMLDialogElement>('dialog[data-comments-report-dialog]')?.open));
    });

    it('opens a folded comment when Show comment is pressed, and moves focus to it', async () => {
        const server = api({ comments: [comment({ uid: 'c1', folded: 'reports', content: { format: 'plain', html: null, text: 'Behind the fold', gifUrl: null, gifWidth: null, gifHeight: null } }), comment({ uid: 'c2' })] });
        const controller = mount(server);
        await settle(() => ready(controller) && Boolean(controller.root.querySelector('[data-comments-action="unfold"]')));

        expect(controller.root.textContent).not.toContain('Behind the fold');

        controller.root.querySelector<HTMLElement>('[data-comments-action="unfold"]')!.click();
        await settle(() => (controller.root.textContent ?? '').includes('Behind the fold'));

        expect(controller.root.querySelector('.comments__folded')).toBeNull();
        expect(document.activeElement).toBe(controller.root.querySelector('[data-comments-comment-uid="c1"] > article'));
    });
});

