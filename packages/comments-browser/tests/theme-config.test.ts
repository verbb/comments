import { describe, expect, it, vi } from 'vitest';
import { CONTROL_NAMES, controlAttributes, createStringView, mergeThemeConfig, renderToString, threadTemplate } from '../src/render';
import { classesIn, scenarios } from './support/scenarios';
import { html } from 'lit-html';
import { attributes } from '../src/directives';
import { threadState } from './support/thread';

/** Every element of one control in some HTML, as its opening tag. */
function controls(html: string, name: string): string[] {
    return [...html.matchAll(/<[a-z]+\s[^>]*data-comments-control="([^"]+)"[^>]*>/g)].filter((match) => match[1] === name).map((match) => match[0]);
}

describe('themeConfig for controls', () => {
    it('reaches each of the ten controls wherever it appears', async () => {
        const drawn = Object.values(await scenarios()).join('');

        for (const name of CONTROL_NAMES) {
            expect(controls(drawn, name).length, name).toBeGreaterThan(0);
        }

        // Spot checks of where each control appears, against the plan's table.
        expect(controls(drawn, 'primaryButton').some((tag) => tag.includes('comments__submit'))).toBe(true);
        expect(controls(drawn, 'primaryButton').some((tag) => tag.includes('data-comments-moderation-action="approve"'))).toBe(true);
        expect(controls(drawn, 'secondaryButton').some((tag) => tag.includes('comments__cancel'))).toBe(true);
        expect(controls(drawn, 'secondaryButton').some((tag) => tag.includes('data-comments-action="load-more"'))).toBe(true);
        expect(controls(drawn, 'secondaryButton').some((tag) => tag.includes('data-comments-action="follow"'))).toBe(true);
        expect(controls(drawn, 'quietButton').some((tag) => tag.includes('data-comments-action="vote"'))).toBe(true);
        expect(controls(drawn, 'quietButton').some((tag) => tag.includes('comments__menu-toggle'))).toBe(true);
        expect(controls(drawn, 'quietButton').some((tag) => tag.includes('data-comments-action="sort"'))).toBe(true);
        expect(controls(drawn, 'quietButton').some((tag) => tag.includes('comments__toggle-replies'))).toBe(true);
        expect(controls(drawn, 'input').some((tag) => tag.includes('name="guest[email]"'))).toBe(true);
        expect(controls(drawn, 'textarea').some((tag) => tag.includes('name="details"'))).toBe(true);
        expect(controls(drawn, 'textarea').some((tag) => tag.includes('data-comments-body'))).toBe(true);
        expect(controls(drawn, 'select').length).toBeGreaterThan(0);
        expect(controls(drawn, 'checkbox').some((tag) => tag.includes('name="guest[notifyReplies]"'))).toBe(true);
        expect(controls(drawn, 'radio').some((tag) => tag.includes('name="reason"'))).toBe(true);
        expect(controls(drawn, 'link').some((tag) => tag.includes('comments__permalink'))).toBe(true);
        expect(controls(drawn, 'avatar').some((tag) => tag.startsWith('<img'))).toBe(true);
    });

    it('adds classes and attributes to every element of a control', async () => {
        const html = renderToString(threadTemplate(createStringView({
            state: await threadState(),
            themeConfig: { quietButton: { attributes: { class: ['btn-ghost', 'btn-sm'], 'data-track': 'comments', title: 3 } } },
        })));
        const quiet = controls(html, 'quietButton');

        expect(quiet.length).toBeGreaterThan(4);
        for (const tag of quiet) {
            expect(tag).toMatch(/class="[^"]*comments__button comments__button--quiet btn-ghost btn-sm"/);
            expect(tag).toContain('data-track="comments"');
            expect(tag).toContain('title="3"');
        }
    });

    it('drops only the styling classes with resetClass, keeping identifying classes and data attributes', () => {
        const config = mergeThemeConfig([{ primaryButton: { resetClass: true, attributes: { class: 'btn btn-primary' } } }]);
        const attributes = controlAttributes(config, 'primaryButton', { identify: 'comments__submit', attributes: { type: 'submit', 'data-comments-action': 'post' } });

        expect(attributes.class).toBe('comments__submit btn btn-primary');
        expect(attributes['data-comments-action']).toBe('post');
        expect(attributes['data-comments-control']).toBe('primaryButton');
        expect(attributes.type).toBe('submit');
    });

    it('sets only classes, a title, an accessible name and a site’s own data attributes, warning about the rest', () => {
        const warn = vi.fn();
        const config = mergeThemeConfig([{
            input: { attributes: { id: 'mine', name: 'x', onclick: 'alert(1)', style: 'color:red', formaction: '/x', srcset: 'a.jpg', 'data-comments-control': 'no', placeholder: 'No', 'aria-label': 'Name', title: 'Yours', 'data-track': 'ok', class: 'form-input' } },
            button: { attributes: {} },
            link: 'btn' as never,
            avatar: { attributes: { title: { toString: () => 'x' } } } as never,
        } as never], warn);
        const attributes = controlAttributes(config, 'input', { attributes: { id: 'theirs', name: 'guest[name]' } });

        expect(attributes).toEqual({ class: 'comments__input form-input', 'aria-label': 'Name', title: 'Yours', 'data-track': 'ok', id: 'theirs', name: 'guest[name]', 'data-comments-control': 'input' });
        expect(warn.mock.calls.map((call) => /"([^"]+)"|themeConfig's (\w+)/.exec(call[0])?.slice(1).find(Boolean))).toEqual([
            'id', 'name', 'onclick', 'style', 'formaction', 'srcset', 'data-comments-control', 'placeholder', 'button', 'link', 'avatar',
        ]);
    });

    it('merges each source in order, later ones winning per control and per attribute', () => {
        const config = mergeThemeConfig([
            { link: { resetClass: true, attributes: { class: 'a', title: 'one' } } },
            { link: { attributes: { class: 'b' } } },
            { link: { resetClass: false } },
        ], () => undefined);

        expect(config.link).toEqual({ resetClass: false, attributes: { class: 'b', title: 'one' } });
    });

    it('never draws an attribute name that could break out of its tag', () => {
        expect(renderToString(html`<p ${attributes({ 'x" onclick="y': 'z', ok: 'yes' })}></p>`)).toBe('<p ok="yes"></p>');
    });

    it('leaves the classes of everything that isn’t a control alone', async () => {
        const html = renderToString(threadTemplate(createStringView({
            state: await threadState(),
            themeConfig: Object.fromEntries(CONTROL_NAMES.map((name) => [name, { resetClass: true }])),
        })));

        expect(classesIn(html)).toContain('comments__comment');
        expect(classesIn(html)).toContain('comments__author-name');
        expect(classesIn(html)).not.toContain('comments__button');
        expect(classesIn(html)).toContain('comments__sort-option');
    });
});
