import { act, cleanup, render } from '@testing-library/react';
import { cookieSession, guestSession, memberToken, type SignInProvider } from '@verbb/comments-core';
import { startTransition, Suspense, useMemo, useRef, useState } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { CommentsProvider, CommentsThread, useCommentForm, useCommentsEvent, useCommentsThread, type CommentsController, type CommentsPartProps } from '../src/index';
import { ENDPOINT, fakeApi, until, validationFailed } from './support/api';

afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
    document.documentElement.className = '';
});

const ready = (container: HTMLElement) => until(() => container.querySelector('[data-comments-root][data-comments-phase="ready"]'));
const token = () => ({ token: 'cmt_x', expiresAt: Math.floor(Date.now() / 1000) + 900 });

describe('sign-in providers and fetches made on every render', () => {
    it('don’t start useCommentsThread() again', async () => {
        const api = fakeApi();
        let renders = 0;
        let tokens = 0;

        function Headless() {
            renders++;
            const thread = useCommentsThread({ ownerId: 109, endpoint: ENDPOINT, fetch: (input, init) => api.fetch(input, init), auth: memberToken({ getToken: async () => { tokens++; return token(); } }) });
            return <p data-status={thread.state.status}>{thread.roots.length}</p>;
        }

        const { container } = render(<Headless />);
        await until(() => container.querySelector('p[data-status="ready"]'));
        await new Promise((resolve) => setTimeout(resolve, 200));

        expect(renders).toBeLessThan(10);
        expect(api.of('thread')).toHaveLength(1);
        expect(tokens).toBeLessThanOrEqual(2);
        expect(api.of('viewer')[0]!.headers.get('authorization')).toBe('Bearer cmt_x');
    });

    it('don’t start <CommentsThread> again, so a half-written comment stays', async () => {
        const api = fakeApi();
        const destroyed = vi.fn();
        let bump = () => undefined as void;

        function Page() {
            const [n, setN] = useState(0);
            bump = () => setN((value) => value + 1);
            const auth = memberToken({ getToken: async () => token() });

            return (
                <CommentsProvider endpoint={ENDPOINT} fetch={(input, init) => api.fetch(input, init)} auth={auth} nonce={`n-${n}`}>
                    <p>{n}</p>
                    <CommentsThread ownerId={109} siteId={1} loadMode="eager" onDestroyed={destroyed} />
                </CommentsProvider>
            );
        }

        const { container } = render(<Page />);
        await ready(container);
        const textarea = await until(() => container.querySelector<HTMLTextAreaElement>('textarea[name="content"]'));
        textarea.value = 'half-written comment';

        for (let i = 0; i < 3; i++) await act(async () => bump());
        await new Promise((resolve) => setTimeout(resolve, 50));

        expect(destroyed).not.toHaveBeenCalled();
        expect(api.of('thread')).toHaveLength(1);
        expect(container.querySelector<HTMLTextAreaElement>('textarea[name="content"]')).toBe(textarea);
        expect(textarea.value).toBe('half-written comment');
    });

    it('start the thread again for another kind of sign-in', async () => {
        const api = fakeApi();
        const destroyed = vi.fn();
        const member = memberToken({ getToken: async () => token() });
        const ui = (auth: typeof member) => (
            <CommentsProvider endpoint={ENDPOINT} fetch={api.fetch} auth={auth}>
                <CommentsThread ownerId={109} siteId={1} loadMode="eager" onDestroyed={destroyed} />
            </CommentsProvider>
        );
        const { container, rerender } = render(ui(member));

        await ready(container);
        rerender(ui(guestSession()));
        await until(() => api.of('thread').length === 2);

        expect(destroyed).toHaveBeenCalledTimes(1);
        expect(api.of('viewer').at(-1)!.headers.get('x-comments-profile')).toBe('cross-origin-public');
    });
});

describe('a render React never commits', () => {
    it('never reaches the thread', async () => {
        const api = fakeApi();
        const never = new Promise(() => undefined);
        let setOwner = (_owner: number) => undefined as void;

        function Suspends({ on }: { on: boolean }) {
            if (on) throw never;
            return null;
        }

        function App() {
            const [owner, set] = useState(109);
            setOwner = set;

            return (
                <CommentsProvider endpoint={ENDPOINT} fetch={api.fetch}>
                    <Suspense fallback={<p>loading</p>}>
                        <CommentsThread ownerId={owner} siteId={1} loadMode="eager" />
                        <Suspends on={owner !== 109} />
                    </Suspense>
                </CommentsProvider>
            );
        }

        const { container } = render(<App />);
        // Before the renderer arrives, a transition renders another owner and suspends for good.
        act(() => startTransition(() => setOwner(999)));
        await until(() => api.of('thread').length > 0);
        await new Promise((resolve) => setTimeout(resolve, 100));

        expect(container.querySelector('section[data-comments-root]')!.id).toBe('comments-109-1');
        expect(api.of('thread').map((request) => request.url.searchParams.get('ownerId'))).toEqual(['109']);
    });
});

describe('an override that throws', () => {
    it('stays in its own part, with Comments’ markup in its place', async () => {
        const api = fakeApi();
        const errors = vi.spyOn(console, 'error').mockImplementation(() => undefined);

        function Author({ comment }: CommentsPartProps) {
            if (comment!.uid === 'c2') throw new Error('bad author');
            return <i className="ok-author">{comment!.uid}</i>;
        }

        const { container } = render(
            <div>
                <nav id="site-nav">Nav</nav>
                <CommentsProvider endpoint={ENDPOINT} fetch={api.fetch}>
                    <CommentsThread ownerId={109} siteId={1} loadMode="eager" parts={{ authorName: Author }} />
                </CommentsProvider>
            </div>,
        );

        await until(() => container.querySelectorAll('.ok-author').length === 3);
        await until(() => container.querySelector('[data-comments-host="comment:c2:authorName"]')?.textContent?.includes('Author c2'));

        expect(container.querySelector('#site-nav')).not.toBeNull();
        expect(container.querySelectorAll('article[id^="comment-"]')).toHaveLength(4);
        expect(errors.mock.calls.some(([message]) => String(message).includes('"authorName" override threw'))).toBe(true);
    });
});

describe('useCommentForm()', () => {
    function Form({ fail = 0 }: { fail?: number }) {
        const thread = useCommentsThread({ ownerId: 109 });
        const form = useCommentForm({ thread });
        const [text, setText] = useState('Hello');

        return (
            <form ref={form.ref} data-ready={thread.state.status} data-fail={fail} onSubmit={(event) => form.handleSubmit(event).then((response) => { if (response) setText(''); })}>
                <textarea name="content" value={text} onChange={(event) => setText(event.target.value)} />
                <input name="guest[name]" defaultValue="Aroha" />
                <button type="submit">Post</button>
                <span id="error">{form.error}</span>
            </form>
        );
    }

    it('leaves a controlled input to the app, and keeps a guest’s name', async () => {
        const api = fakeApi();
        const { container } = render(<CommentsProvider endpoint={ENDPOINT} fetch={api.fetch}><Form /></CommentsProvider>);

        await until(() => container.querySelector('form[data-ready="ready"]'));
        await act(async () => container.querySelector('button')!.click());
        await until(() => api.of('create').length === 1);
        await until(() => container.querySelector('textarea')!.value === '');

        expect(api.of('create')[0]!.body.content.text).toBe('Hello');
        expect(container.querySelector<HTMLInputElement>('[name="guest[name]"]')!.value).toBe('Aroha');
    });

    it('tries the same comment again with the same operation ID, and a changed one with a new ID', async () => {
        let refusals = 1;
        const api = fakeApi({ override: { create: () => (refusals-- > 0 ? validationFailed({ content: ['Try again.'] }) : undefined) } });
        const { container } = render(<CommentsProvider endpoint={ENDPOINT} fetch={api.fetch}><Form /></CommentsProvider>);

        await until(() => container.querySelector('form[data-ready="ready"]'));
        await act(async () => container.querySelector('button')!.click());
        await until(() => container.querySelector('#error')!.textContent);
        await act(async () => container.querySelector('button')!.click());
        await until(() => api.of('create').length === 2);

        const [first, second] = api.of('create');
        expect(second!.body.operationId).toBe(first!.body.operationId);

        // Posted, so the next comment is a new operation.
        await until(() => container.querySelector('textarea')!.value === '');
        const textarea = container.querySelector('textarea')!;
        await act(async () => {
            Object.getOwnPropertyDescriptor(HTMLTextAreaElement.prototype, 'value')!.set!.call(textarea, 'Another');
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
        });
        await act(async () => container.querySelector('button')!.click());
        await until(() => api.of('create').length === 3);
        expect(api.of('create')[2]!.body.operationId).not.toBe(first!.body.operationId);
    });
});

describe('props that change after the thread starts', () => {
    it('falls back to the provider’s theme, then light, when theme goes', async () => {
        const api = fakeApi();
        const ui = (theme?: 'dark') => (
            <CommentsProvider endpoint={ENDPOINT} fetch={api.fetch}>
                <CommentsThread ownerId={109} siteId={1} loadMode="eager" theme={theme} />
            </CommentsProvider>
        );
        const { container, rerender } = render(ui('dark'));

        await ready(container);
        const root = container.querySelector<HTMLElement>('section')!;
        expect(root.dataset.commentsTheme).toBe('dark');

        rerender(ui());
        expect(root.dataset.commentsTheme).toBe('light');
    });

    it('keeps the drawn thread when count changes, which is read when it starts', async () => {
        const api = fakeApi();
        const ui = (count: number) => (
            <CommentsProvider endpoint={ENDPOINT} fetch={api.fetch}>
                <CommentsThread ownerId={109} siteId={1} loadMode="eager" count={count} />
            </CommentsProvider>
        );
        const { container, rerender } = render(ui(3));

        await ready(container);
        const article = container.querySelector('article');
        rerender(ui(12));

        expect(container.querySelector('article')).toBe(article);
        expect(container.querySelector<HTMLElement>('section')!.dataset.commentsCount).toBe('12');
        expect(api.of('thread')).toHaveLength(1);
    });

    it('follows darkSelector, as Comments.configure() does', async () => {
        const api = fakeApi();
        const { container } = render(
            <CommentsProvider endpoint={ENDPOINT} fetch={api.fetch} darkSelector="html.dark">
                <CommentsThread ownerId={109} siteId={1} loadMode="eager" />
            </CommentsProvider>,
        );

        await ready(container);
        const root = container.querySelector<HTMLElement>('section')!;
        expect(root.dataset.commentsTheme).toBe('light');

        document.documentElement.classList.add('dark');
        await until(() => root.dataset.commentsTheme === 'dark');
        document.documentElement.classList.remove('dark');
        await until(() => root.dataset.commentsTheme === 'light');
    });

    it('reaches the renderer’s new thread after a morph took the old one away', async () => {
        const api = fakeApi();
        const ui = (labels?: Record<string, string>) => (
            <CommentsProvider endpoint={ENDPOINT} fetch={api.fetch}>
                <CommentsThread ownerId={109} siteId={1} loadMode="eager" labels={labels} />
            </CommentsProvider>
        );
        const { container, rerender } = render(ui());

        await ready(container);
        const root = container.querySelector<HTMLElement>('section')!;
        // A morphing page swap puts the server's empty mount point back over the drawn thread.
        root.replaceChildren(Object.assign(document.createElement('h2'), { textContent: 'Comments' }));
        await until(() => root.querySelectorAll('article[id^="comment-"]').length === 4);

        rerender(ui({ reply: 'Answer' }));
        await until(() => container.querySelector('[data-comments-action="reply"]')?.textContent?.includes('Answer'));
    });
});

describe('useCommentsEvent() with a ref', () => {
    it('follows the ref to a new element', async () => {
        const heard = vi.fn();
        let swap = () => undefined as void;

        function Listener() {
            const ref = useRef<HTMLDivElement>(null);
            const [second, setSecond] = useState(false);
            swap = () => setSecond(true);
            useCommentsEvent('comments:mounted', heard, { target: ref });

            return second ? <div key="b" id="b" ref={ref} /> : <div key="a" id="a" ref={ref} />;
        }

        const { container } = render(<Listener />);
        await act(async () => swap());
        container.querySelector('#b')!.dispatchEvent(new CustomEvent('comments:mounted', { detail: {} }));

        expect(heard).toHaveBeenCalledTimes(1);
    });
});

describe('signing out through the latest provider', () => {
    for (const [label, memo] of [['made on every render', false], ['made once', true]] as const) {
        it(`reaches the hook and the thread when the provider is ${label}`, async () => {
            const api = fakeApi();
            let latest: SignInProvider | null = null;
            let bump = () => undefined as void;

            function Page() {
                const [n, setN] = useState(0);
                bump = () => setN((value) => value + 1);
                const inline = cookieSession();
                const once = useMemo(() => cookieSession(), []);
                const auth = memo ? once : inline;
                latest = auth;
                const thread = useCommentsThread({ ownerId: 109, endpoint: ENDPOINT, fetch: api.fetch, auth });

                return (
                    <CommentsProvider endpoint={ENDPOINT} fetch={api.fetch} auth={auth}>
                        <p data-status={thread.state.status} data-n={n} />
                        <CommentsThread ownerId={109} siteId={1} loadMode="eager" />
                    </CommentsProvider>
                );
            }

            const { container } = render(<Page />);
            await until(() => container.querySelector('p[data-status="ready"]'));
            await ready(container);
            const before = api.of('viewer').length;

            // The app renders again for something else, with no request meanwhile, then signs out.
            await act(async () => bump());
            await act(async () => bump());
            await act(async () => latest!.reset!());

            // Both the hook's store and the thread read the viewer again.
            await until(() => api.of('viewer').length >= before + 2);
        });
    }
});

describe('a failed override whose comment goes', () => {
    it('has its host dropped with the comment', async () => {
        const api = fakeApi();
        let controller: CommentsController | undefined;
        vi.spyOn(console, 'error').mockImplementation(() => undefined);

        function Author({ comment }: CommentsPartProps) {
            if (comment!.uid === 'c3') throw new Error('bad author');
            return <i className="ok-author">{comment!.uid}</i>;
        }

        const { container } = render(
            <CommentsProvider endpoint={ENDPOINT} fetch={api.fetch}>
                <CommentsThread ownerId={109} siteId={1} loadMode="eager" parts={{ authorName: Author }} onMounted={(event) => { controller = event.detail.controller; }} />
            </CommentsProvider>,
        );

        await until(() => container.querySelector('[data-comments-host="comment:c3:authorName"]')?.textContent?.includes('Author c3'));
        await act(async () => { await controller!.store.delete('c3'); });

        await until(() => !controller!.getHosts().some((host) => host.key.startsWith('comment:c3:')));
        expect(container.querySelector('[data-comments-host="comment:c3:authorName"]')).toBeNull();
        expect(container.querySelectorAll('.ok-author')).toHaveLength(3);
    });
});
