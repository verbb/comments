import { describe, expect, it } from 'vitest';
import { createFormView, createStringView, formTemplate, renderToString, threadTemplate } from '../src/render';
import { threadState } from './support/thread';

/**
 * The site's **Show Avatar** setting reaches the thread as `conversation.showAvatars`. Off, no avatar
 * is drawn on comments, the form or its placeholder, and the regions are marked so the theme closes up
 * the avatar column.
 */
describe('Show Avatar', () => {
    it('draws avatars and no marker while the setting is on', async () => {
        const state = await threadState();
        const html = renderToString(threadTemplate(createStringView({ state })));

        expect(html).toContain('data-comments-control="avatar"');
        expect(html).not.toContain('data-comments-avatars');
    });

    it('draws no avatars and marks the regions while it is off', async () => {
        const state = await threadState({ conversation: { showAvatars: false } });
        const html = renderToString(threadTemplate(createStringView({ state })));

        expect(html).not.toContain('data-comments-control="avatar"');
        expect(html).toContain('data-comments-region="editor" data-comments-avatars="off"');
        expect(html).toContain('data-comments-region="list" data-comments-avatars="off"');
    });

    it('leaves the avatar out of a form while it is off', async () => {
        const on = await threadState();
        const off = await threadState({ conversation: { showAvatars: false } });

        expect(renderToString(formTemplate(createFormView(createStringView({ state: on }), 'create', null)))).toContain('data-comments-control="avatar"');
        expect(renderToString(formTemplate(createFormView(createStringView({ state: off }), 'create', null)))).not.toContain('data-comments-control="avatar"');
    });
});
