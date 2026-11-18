import { mount } from '@vue/test-utils';
import type { CommentsController } from '@verbb/comments-browser';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h, onMounted, onUnmounted, ref, type PropType } from 'vue';
import { CommentsPlugin, CommentsThread } from '../src/index';
import { comment, ENDPOINT, fakeApi, until } from './support/api';

/**
 * A re-sort holds the comments past the first batch for later ones, with their component hosts out
 * of the page meanwhile. A Teleport into such a host must keep its component, and its state, until
 * the host goes back in.
 */

const mounted: Array<{ unmount(): void }> = [];

afterEach(() => {
    mounted.splice(0).forEach((wrapper) => wrapper.unmount());
    document.body.replaceChildren();
    vi.restoreAllMocks();
});

const lifecycle = { mounted: 0, unmounted: 0 };

const Content = defineComponent({
    props: { comment: { type: Object as PropType<{ uid: string }>, required: true } },
    setup(props) {
        const clicks = ref(0);
        onMounted(() => lifecycle.mounted++);
        onUnmounted(() => lifecycle.unmounted++);

        return () => h('span', { class: 'mine', 'data-uid': props.comment.uid }, [
            h('button', { type: 'button', class: 'count', onClick: () => clicks.value++ }, String(clicks.value)),
            h('input', { class: 'draft' }),
        ]);
    },
});

describe('a re-sort with a component on every comment', () => {
    it('keeps each Teleport, with its component’s state, while its host waits out of the page for a later batch', async () => {
        const roots = Array.from({ length: 120 }, (_, index) => comment(`r${index}`));
        const api = fakeApi({ roots });
        const warnings = vi.spyOn(console, 'warn');
        let controller: CommentsController | undefined;
        const Page = defineComponent({
            setup() {
                return () => h(CommentsThread, { ownerId: 109, siteId: 1, loadMode: 'eager', parts: { content: Content }, onMounted: (event: CustomEvent) => { controller = event.detail.controller; } });
            },
        });
        const wrapper = mount(Page, { attachTo: document.body, global: { plugins: [[CommentsPlugin, { endpoint: ENDPOINT, fetch: api.fetch }]] } });
        mounted.push(wrapper);
        const container = wrapper.element.parentElement as HTMLElement;

        await until(() => container.querySelectorAll('.mine').length === 120, 8000);
        const first = container.querySelector<HTMLElement>('.mine[data-uid="r0"]')!;
        first.querySelector<HTMLButtonElement>('.count')!.click();
        await until(() => first.querySelector('.count')!.textContent === '1');
        first.querySelector<HTMLInputElement>('.draft')!.value = 'typed';
        lifecycle.mounted = 0;
        lifecycle.unmounted = 0;

        roots.reverse();
        controller!.refresh({ sort: 'oldest' });

        // After the first batch, r0 comes last, so its host waits out of the page, its Teleport kept.
        await until(() => container.querySelector('[data-comments-comment]')?.getAttribute('data-comments-comment-uid') === 'r119', 8000);
        const waiting = controller!.getHosts().find((host) => host.key === 'comment:r0:content')!;
        expect(waiting.element.isConnected).toBe(false);
        expect(waiting.element.contains(first)).toBe(true);

        await until(() => container.querySelectorAll('.mine').length === 120, 8000);
        await new Promise((resolve) => setTimeout(resolve, 100));

        const again = container.querySelector<HTMLElement>('.mine[data-uid="r0"]')!;
        expect(again).toBe(first);
        expect(again.querySelector('.count')!.textContent).toBe('1');
        expect(again.querySelector<HTMLInputElement>('.draft')!.value).toBe('typed');
        expect(lifecycle).toEqual({ mounted: 0, unmounted: 0 });
        // Vue says nothing about a Teleport whose target is out of the page (lit's development notice aside).
        expect(warnings.mock.calls.filter((call) => !String(call[0]).startsWith('Lit is in dev mode'))).toEqual([]);
    }, 30000);
});
