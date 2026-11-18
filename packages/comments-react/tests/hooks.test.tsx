import { act, cleanup, render } from '@testing-library/react';
import { StrictMode, useState } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { CommentsProvider, CommentsThread, useCommentForm, useCommentsEvent, useCommentsThread, type UseCommentsThreadResult } from '../src/index';
import { ENDPOINT, fakeApi, until, validationFailed } from './support/api';

afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
});

let thread: UseCommentsThreadResult;

function List({ ownerUri = 'blog/hello' }: { ownerUri?: string }) {
    thread = useCommentsThread({ ownerUri });

    return (
        <ul data-status={thread.state.status}>
            {thread.roots.map((comment) => (
                <li key={comment.uid} data-uid={comment.uid}>
                    {comment.content?.text} ({comment.interaction?.upvotes})
                    {thread.replies(comment.uid).map((reply) => <span key={reply.uid} className="reply">{reply.uid}</span>)}
                </li>
            ))}
        </ul>
    );
}

describe('useCommentsThread()', () => {
    it('loads the thread on mount and gives its state and roots', async () => {
        const api = fakeApi();
        const { container } = render(<CommentsProvider endpoint={ENDPOINT} fetch={api.fetch}><List /></CommentsProvider>);

        expect(container.querySelector('ul')!.dataset.status).toBe('loading');
        await until(() => container.querySelector('ul[data-status="ready"]'));

        expect(Array.from(container.querySelectorAll('li'), (item) => item.dataset.uid)).toEqual(['c1', 'c2', 'c3']);
        expect(container.querySelector('.reply')!.textContent).toBe('c2-1');
        expect(api.of('thread')[0]!.url.searchParams.get('ownerUri')).toBe('blog/hello');
        expect(api.of('viewer')).toHaveLength(1);
    });

    it('runs actions through the store, and the state follows', async () => {
        const api = fakeApi();
        const { container } = render(<CommentsProvider endpoint={ENDPOINT} fetch={api.fetch}><List /></CommentsProvider>);

        await until(() => container.querySelector('ul[data-status="ready"]'));
        await act(() => thread.vote('c1', 'upvote').then(() => undefined));

        expect(api.of('vote')[0]!.body).toMatchObject({ uid: 'c1', vote: 'upvote' });
        expect(container.querySelector('[data-uid="c1"]')!.textContent).toContain('(3)');
        expect(thread.state.permissions.c1!.vote).toBe('upvote');
    });

    it('makes one store under StrictMode, and a new one for a new owner', async () => {
        const api = fakeApi();
        const { container, rerender } = render(<StrictMode><CommentsProvider endpoint={ENDPOINT} fetch={api.fetch}><List /></CommentsProvider></StrictMode>);

        await until(() => container.querySelector('ul[data-status="ready"]'));
        const first = thread.store;
        // StrictMode starts the effect twice; the first store is destroyed before its answer lands.
        expect(api.of('thread').length).toBeLessThanOrEqual(2);

        rerender(<StrictMode><CommentsProvider endpoint={ENDPOINT} fetch={api.fetch}><List ownerUri="blog/other" /></CommentsProvider></StrictMode>);
        await until(() => thread.store !== first && container.querySelector('ul[data-status="ready"]'));

        expect(api.requests.filter((request) => request.action === 'thread').at(-1)!.url.searchParams.get('ownerUri')).toBe('blog/other');
    });

    it('refuses actions before it has mounted in the browser', async () => {
        let early: Promise<unknown> | null = null;

        function Early() {
            const result = useCommentsThread({ ownerId: 1, autoLoad: false });
            early ??= result.vote('c1', 'upvote').catch((error: Error) => error.message);
            return null;
        }

        render(<Early />);
        expect(await early).toMatch(/before the thread mounted/);
    });
});

function Form({ mode = 'create', parentUid }: { mode?: 'create' | 'reply'; parentUid?: string }) {
    const local = useCommentsThread({ ownerId: 109 });
    const form = useCommentForm({ thread: local, mode, parentUid });
    const [result, setResult] = useState('');

    return (
        <form ref={form.ref} onSubmit={(event) => form.handleSubmit(event).then((response) => setResult(response ? response.comment.uid : 'refused'))} data-ready={local.state.status}>
            <textarea name="content" defaultValue="Hello there" />
            <input name="fields[rating]" defaultValue="4" />
            {form.fieldErrors.content ? <p className="error">{form.fieldErrors.content[0]}</p> : null}
            {form.error ? <p className="message">{form.error}</p> : null}
            <button type="submit" disabled={form.posting}>Post</button>
            <output>{result}</output>
        </form>
    );
}

describe('useCommentForm()', () => {
    it('posts what the form holds, with Protect’s evidence, and empties the form', async () => {
        const api = fakeApi({ protect: true });
        const { container } = render(<CommentsProvider endpoint={ENDPOINT} fetch={api.fetch}><Form /></CommentsProvider>);

        await until(() => container.querySelector('form[data-ready="ready"]'));
        const textarea = container.querySelector('textarea')!;

        // Starting to write fetches a preparation and mounts Protect on the form.
        await act(async () => textarea.dispatchEvent(new FocusEvent('focusin', { bubbles: true })));
        await until(() => api.of('protection').length >= 1 && container.querySelector('form [name="a1b2c3"], form .protect-honeypot'));

        await act(async () => container.querySelector('button')!.click());
        await until(() => container.querySelector('output')!.textContent);

        const [create] = api.of('create');
        expect(create!.body).toMatchObject({ ownerId: 109, parentUid: null, content: { format: 'plain', text: 'Hello there' }, fields: { rating: '4' } });
        expect(create!.body.protection).toBeTruthy();
        expect(container.querySelector('output')!.textContent).toMatch(/^new-/);
        expect(textarea.value).toBe('');
    });

    it('gives the server’s field errors and message, and says when it’s posting', async () => {
        const api = fakeApi({ override: { create: () => validationFailed({ content: ['Say a little more.'] }) } });
        const { container } = render(<CommentsProvider endpoint={ENDPOINT} fetch={api.fetch}><Form /></CommentsProvider>);

        await until(() => container.querySelector('form[data-ready="ready"]'));
        await act(async () => container.querySelector('button')!.click());

        await until(() => container.querySelector('.error'));
        expect(container.querySelector('.error')!.textContent).toBe('Say a little more.');
        expect(container.querySelector('.message')!.textContent).toBeTruthy();
        expect(container.querySelector('output')!.textContent).toBe('refused');
        expect(container.querySelector('button')!.disabled).toBe(false);
    });

    it('posts a reply under its parent', async () => {
        const api = fakeApi();
        const { container } = render(<CommentsProvider endpoint={ENDPOINT} fetch={api.fetch}><Form mode="reply" parentUid="c2" /></CommentsProvider>);

        await until(() => container.querySelector('form[data-ready="ready"]'));
        await act(async () => container.querySelector('button')!.click());
        await until(() => api.of('create').length === 1);

        expect(api.of('create')[0]!.body.parentUid).toBe('c2');
    });
});

describe('useCommentsEvent()', () => {
    it('hears the browser events of every thread on the page, or only one', async () => {
        const api = fakeApi();
        const all = vi.fn();
        const one = vi.fn();
        const other = vi.fn();

        function Listener() {
            useCommentsEvent('comments:roots-loaded', all);
            useCommentsEvent('comments:roots-loaded', one, { instanceId: 'comments-109-1' });
            useCommentsEvent('comments:roots-loaded', other, { instanceId: 'comments-elsewhere' });
            return null;
        }

        render(
            <CommentsProvider endpoint={ENDPOINT} fetch={api.fetch}>
                <Listener />
                <CommentsThread ownerId={109} siteId={1} loadMode="eager" />
            </CommentsProvider>,
        );

        await until(() => all.mock.calls.length === 1);
        expect(all.mock.calls[0]![0]).toBeInstanceOf(CustomEvent);
        expect(one).toHaveBeenCalledTimes(1);
        expect(other).not.toHaveBeenCalled();
    });

    it('hears a headless thread’s store', async () => {
        const api = fakeApi();
        const created = vi.fn();

        function Headless() {
            const local = useCommentsThread({ ownerId: 109 });
            useCommentsEvent('comments:comment-created', created, { thread: local });
            thread = local;
            return null;
        }

        render(<CommentsProvider endpoint={ENDPOINT} fetch={api.fetch}><Headless /></CommentsProvider>);
        await until(() => thread.state.status === 'ready');
        await act(() => thread.create({ content: { format: 'plain', text: 'Hi' } }).then(() => undefined));

        expect(created).toHaveBeenCalledTimes(1);
        expect(created.mock.calls[0]![0]).toMatchObject({ apiVersion: 1, parentUid: null });
    });
});
