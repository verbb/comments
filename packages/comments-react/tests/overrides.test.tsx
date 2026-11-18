import { act, cleanup, render } from '@testing-library/react';
import type { CommentsController } from '@verbb/comments-browser';
import { createContext, useContext, useEffect, useLayoutEffect, useRef } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { CommentsProvider, CommentsThread, templatePart, type CommentsFieldInputProps, type CommentsPartProps } from '../src/index';
import { ENDPOINT, fakeApi, until, validationFailed } from './support/api';

afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
});

const Tone = createContext('plain');
const lifecycle = { mounted: [] as string[], unmounted: [] as string[] };

function Author({ comment, actions }: CommentsPartProps) {
    const tone = useContext(Tone);
    const uid = comment!.uid;

    useEffect(() => {
        lifecycle.mounted.push(uid);
        return () => void lifecycle.unmounted.push(uid);
    }, [uid]);

    return (
        <b className="custom-author" data-uid={uid} data-tone={tone}>
            {comment!.author!.name} ({comment!.interaction?.upvotes})
            <button type="button" className="custom-like" onClick={() => void (actions as { vote(value: 'upvote'): Promise<void> }).vote('upvote')}>Like</button>
        </b>
    );
}

function Meta({ renderDefaultInto }: CommentsPartProps) {
    const element = useRef<HTMLSpanElement>(null);
    useLayoutEffect(() => {
        renderDefaultInto(element.current!);
    });
    return <span className="custom-meta" ref={element} />;
}

async function mount(props: Record<string, unknown> = {}, api = fakeApi()) {
    let controller: CommentsController | undefined;
    const result = render(
        <Tone.Provider value="warm">
            <CommentsProvider endpoint={ENDPOINT} fetch={api.fetch}>
                <CommentsThread ownerId={109} siteId={1} loadMode="eager" onMounted={(event) => { controller = event.detail.controller; }} {...props} />
            </CommentsProvider>
        </Tone.Provider>,
    );

    await until(() => result.container.querySelector('[data-comments-phase="ready"]'));
    return { ...result, api, controller: () => controller! };
}

describe('component part overrides', () => {
    it('render each part through a portal, inside the app’s own tree', async () => {
        lifecycle.mounted = [];
        const { container } = await mount({ parts: { authorName: Author } });

        await until(() => container.querySelectorAll('.custom-author').length === 4);
        const author = container.querySelector<HTMLElement>('.custom-author[data-uid="c1"]')!;

        expect(author.dataset.tone).toBe('warm');
        expect(author.parentElement!.localName).toBe('verbb-comments-host');
        expect(author.closest('article#comment-' + author.closest('article')!.id.slice(8))).toBeTruthy();
        expect(author.textContent).toContain('Author c1 (2)');
        expect(lifecycle.mounted.sort()).toEqual(['c1', 'c2', 'c2-1', 'c3']);
    });

    it('keep the portal when the renderer redraws the comment, with new props', async () => {
        lifecycle.mounted = [];
        lifecycle.unmounted = [];
        const { container, api } = await mount({ parts: { authorName: Author } });

        await until(() => container.querySelectorAll('.custom-author').length === 4);
        const host = container.querySelector('.custom-author[data-uid="c1"]')!.parentElement;
        const before = container.querySelector('.custom-author[data-uid="c1"]');

        // The component's own button calls the comment's bound action.
        await act(async () => container.querySelector<HTMLButtonElement>('.custom-author[data-uid="c1"] .custom-like')!.click());
        await until(() => container.querySelector('.custom-author[data-uid="c1"]')!.textContent!.includes('(3)'));

        expect(api.of('vote')[0]!.body).toMatchObject({ uid: 'c1', vote: 'upvote' });
        expect(container.querySelector('.custom-author[data-uid="c1"]')!.parentElement).toBe(host);
        expect(container.querySelector('.custom-author[data-uid="c1"]')).toBe(before);
        expect(lifecycle.mounted.filter((uid) => uid === 'c1')).toHaveLength(1);
        expect(lifecycle.unmounted).toEqual([]);
    });

    it('drop the portal when the renderer removes the comment', async () => {
        lifecycle.unmounted = [];
        const { container, controller } = await mount({ parts: { authorName: Author } });

        await until(() => container.querySelectorAll('.custom-author').length === 4);
        await act(async () => { await controller().store.delete('c3'); });

        await until(() => !container.querySelector('.custom-author[data-uid="c3"]'));
        expect(lifecycle.unmounted).toEqual(['c3']);
        expect(container.querySelectorAll('.custom-author')).toHaveLength(3);
        expect(container.querySelector('#comment-c3, [data-comments-comment-uid="c3"]')).toBeNull();
    });

    it('unmount every portal with the thread', async () => {
        lifecycle.unmounted = [];
        const { container, unmount } = await mount({ parts: { authorName: Author } });

        await until(() => container.querySelectorAll('.custom-author').length === 4);
        unmount();

        expect(lifecycle.unmounted.sort()).toEqual(['c1', 'c2', 'c2-1', 'c3']);
    });

    it('can draw Comments’ own markup inside the component with renderDefaultInto()', async () => {
        const { container } = await mount({ parts: { meta: Meta } });

        await until(() => container.querySelectorAll('.custom-meta').length === 4);
        await until(() => container.querySelector('.custom-meta')!.textContent!.includes('2 hours ago'));
    });

    it('take a templatePart() function or a custom element’s name as the renderer would', async () => {
        const { container } = await mount({
            parts: {
                authorName: templatePart(({ comment, html }) => html`<i class="cheap-author">${(comment as { uid: string }).uid}</i>`),
                avatar: 'acme-avatar',
            },
        });

        await until(() => container.querySelectorAll('.cheap-author').length === 4);
        expect(container.querySelectorAll('acme-avatar')).toHaveLength(4);
        expect(container.querySelector('verbb-comments-host')).toBeNull();
    });

    it('swap in place when the parts change, without starting the thread again', async () => {
        const { container, rerender, api } = await mount({ parts: { authorName: Author } });

        await until(() => container.querySelectorAll('.custom-author').length === 4);
        rerender(
            <Tone.Provider value="warm">
                <CommentsProvider endpoint={ENDPOINT} fetch={api.fetch}>
                    <CommentsThread ownerId={109} siteId={1} loadMode="eager" parts={{ authorName: templatePart(({ html }) => html`<i class="cheap-author">x</i>`) }} />
                </CommentsProvider>
            </Tone.Provider>,
        );

        await until(() => container.querySelectorAll('.cheap-author').length === 4);
        expect(container.querySelectorAll('.custom-author')).toHaveLength(0);
        expect(api.of('thread')).toHaveLength(1);
    });
});

function Stars({ value, setValue, error, id, labelId }: CommentsFieldInputProps) {
    return (
        <div id={id} role="radiogroup" aria-labelledby={labelId} aria-invalid={error ? 'true' : undefined} className="stars">
            {[1, 2, 3, 4, 5].map((n) => (
                <button key={n} type="button" data-star={n} aria-pressed={Number(value) >= n} onClick={() => setValue(n)}>★</button>
            ))}
        </div>
    );
}

const RATING = { handle: 'rating', uid: 'f-rating', label: 'Rating', instructions: null, required: false, input: 'number', multiple: false, options: [], attributes: {}, show: true, display: 'inline' };

describe('a field input component', () => {
    it('posts the value it sets', async () => {
        const api = fakeApi({ fields: { comment: [RATING] } });
        const { container } = await mount({ fields: { rating: { input: Stars } } }, api);

        const star = await until(() => container.querySelector<HTMLButtonElement>('form .stars [data-star="4"]'));
        await act(async () => star.click());
        await until(() => container.querySelector('form .stars [data-star="4"][aria-pressed="true"]'));

        const form = star.closest('form')!;
        const textarea = form.querySelector<HTMLTextAreaElement>('textarea[name="content"]')!;
        await act(async () => {
            textarea.value = 'Four stars';
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
        });
        await act(async () => form.requestSubmit());
        await until(() => api.of('create').length === 1);

        expect(api.of('create')[0]!.body).toMatchObject({ content: { format: 'plain', text: 'Four stars' }, fields: { rating: 4 } });
    });

    it('gets the server’s error for its field', async () => {
        const api = fakeApi({ fields: { comment: [RATING] }, override: { create: () => validationFailed({ 'fields.rating': ['Pick at least two stars.'] }) } });
        const { container } = await mount({ fields: { rating: { input: Stars } } }, api);

        const star = await until(() => container.querySelector<HTMLButtonElement>('form .stars [data-star="1"]'));
        await act(async () => star.click());
        const form = star.closest('form')!;
        const textarea = form.querySelector<HTMLTextAreaElement>('textarea[name="content"]')!;
        await act(async () => {
            textarea.value = 'One star';
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
        });
        await act(async () => form.requestSubmit());

        await until(() => form.querySelector('.stars[aria-invalid="true"]'));
        expect(form.textContent).toContain('Pick at least two stars.');
    });
});
