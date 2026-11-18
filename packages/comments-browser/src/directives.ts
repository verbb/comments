import { noChange, nothing } from 'lit-html';
import { Directive, directive, PartType, type ChildPart, type ElementPart, type PartInfo } from 'lit-html/directive.js';
import { repeat } from 'lit-html/directives/repeat.js';
import { trustedHtml } from './trusted.js';

/**
 * Comments' own lit-html directives, each with a string form so `renderToString()` can draw the
 * same template without a DOM.
 */
export type StringDirective = {
    /** Attributes for an element part. */
    attributes?(values: unknown[]): Record<string, string | boolean | null | undefined>;
    /** Inner HTML for an element part, added once its tag closes. */
    inner?(values: unknown[]): string | undefined;
    /** What a child part draws. */
    child?(values: unknown[]): unknown;
};

const stringForms = new Map<unknown, StringDirective>();

export function directiveClass(type: unknown): StringDirective | undefined {
    return stringForms.get(type);
}

export type AttributeMap = Record<string, string | true>;

/**
 * Sets a map of attributes on an element and removes the ones it set last time that are gone now,
 * so control classes and `themeConfig` attributes stay exactly as resolved. Attributes it never set,
 * such as `aria-busy` from the script or a site's own, are left alone.
 */
// Attribute names already checked, since the same few names come up on every comment.
const validNames = new Set<string>();

class AttributesDirective extends Directive {
    private applied = new Map<string, string>();

    constructor(info: PartInfo) {
        super(info);

        if (info.type !== PartType.ELEMENT) {
            throw new Error('Comments: attributes() only works on an element.');
        }
    }

    render(_attributes: AttributeMap): typeof nothing {
        return nothing;
    }

    override update(part: ElementPart, [attributes]: [AttributeMap]): typeof noChange {
        const element = part.element;
        const next = new Map<string, string>();

        for (const [name, value] of Object.entries(attributes)) {
            if (validNames.has(name) || (/^[a-zA-Z_:][-a-zA-Z0-9_:.]*$/.test(name) && validNames.add(name))) next.set(name, value === true ? '' : value);
        }

        for (const name of this.applied.keys()) {
            if (!next.has(name)) element.removeAttribute(name);
        }

        for (const [name, value] of next) {
            if (this.applied.get(name) !== value || element.getAttribute(name) !== value) {
                element.setAttribute(name, value);
            }
        }

        this.applied = next;
        return noChange;
    }
}

export const attributes = directive(AttributesDirective);

stringForms.set(AttributesDirective, {
    attributes: ([map]) => map as AttributeMap,
});

/**
 * The sanitised comment body, the only raw HTML the renderer inserts. It goes through the
 * `verbb-comments` Trusted Types policy, so a site enforcing Trusted Types allows that one name
 * rather than lit-html's for everything.
 */
class RichHtmlDirective extends Directive {
    private last: string | undefined;

    constructor(info: PartInfo) {
        super(info);

        if (info.type !== PartType.ELEMENT) {
            throw new Error('Comments: richHtml() only works on an element.');
        }
    }

    render(_html: string): typeof nothing {
        return nothing;
    }

    override update(part: ElementPart, [html]: [string]): typeof noChange {
        if (html !== this.last) {
            this.last = html;
            (part.element as Element & { innerHTML: unknown }).innerHTML = trustedHtml(html);
        }

        return noChange;
    }
}

export const richHtml = directive(RichHtmlDirective);

stringForms.set(RichHtmlDirective, {
    inner: ([html]) => String(html ?? ''),
});

/**
 * A part override given as a custom element's name: the element is created once and given the
 * part's context as properties on every render, so it can draw itself however it likes.
 */
class CustomElementDirective extends Directive {
    private element: HTMLElement | null = null;
    private tag = '';

    constructor(info: PartInfo) {
        super(info);

        if (info.type !== PartType.CHILD) {
            throw new Error('Comments: a custom element part goes where content goes.');
        }
    }

    render(tag: string, _properties: Record<string, unknown>): unknown {
        return tag;
    }

    override update(_part: ChildPart, [tag, properties]: [string, Record<string, unknown>]): unknown {
        if (!this.element || this.tag !== tag) {
            this.tag = tag;
            this.element = document.createElement(tag);
        }

        Object.assign(this.element, properties);
        return this.element;
    }
}

export const customElementPart = directive(CustomElementDirective);

stringForms.set(CustomElementDirective, {
    child: ([tag]) => {
        const name = String(tag);
        return /^[a-z][a-z0-9._-]*-[a-z0-9._-]*$/.test(name) ? { _$litType$: 1, strings: [`<${name}></${name}>`], values: [] } : nothing;
    },
});

// lit-html's own `repeat()`, which keeps each comment's element as the list changes.
const repeatType = (repeat([], () => '') as unknown as { _$litDirective$: unknown })._$litDirective$;

stringForms.set(repeatType, {
    child: (values) => {
        const [items, keyOrTemplate, template] = values as [Iterable<unknown>, (item: unknown, index: number) => unknown, ((item: unknown, index: number) => unknown) | undefined];
        const draw = template ?? keyOrTemplate;
        return Array.from(items, (item, index) => draw(item, index));
    },
});
