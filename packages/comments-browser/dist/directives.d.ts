import { noChange, nothing } from 'lit-html';
import { Directive, type ChildPart, type ElementPart, type PartInfo } from 'lit-html/directive.js';
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
export declare function directiveClass(type: unknown): StringDirective | undefined;
export type AttributeMap = Record<string, string | true>;
declare class AttributesDirective extends Directive {
    private applied;
    constructor(info: PartInfo);
    render(_attributes: AttributeMap): typeof nothing;
    update(part: ElementPart, [attributes]: [AttributeMap]): typeof noChange;
}
export declare const attributes: (_attributes: AttributeMap) => import("lit-html/directive.js").DirectiveResult<typeof AttributesDirective>;
/**
 * The sanitised comment body, the only raw HTML the renderer inserts. It goes through the
 * `verbb-comments` Trusted Types policy, so a site enforcing Trusted Types allows that one name
 * rather than lit-html's for everything.
 */
declare class RichHtmlDirective extends Directive {
    private last;
    constructor(info: PartInfo);
    render(_html: string): typeof nothing;
    update(part: ElementPart, [html]: [string]): typeof noChange;
}
export declare const richHtml: (_html: string) => import("lit-html/directive.js").DirectiveResult<typeof RichHtmlDirective>;
/**
 * A part override given as a custom element's name: the element is created once and given the
 * part's context as properties on every render, so it can draw itself however it likes.
 */
declare class CustomElementDirective extends Directive {
    private element;
    private tag;
    constructor(info: PartInfo);
    render(tag: string, _properties: Record<string, unknown>): unknown;
    update(_part: ChildPart, [tag, properties]: [string, Record<string, unknown>]): unknown;
}
export declare const customElementPart: (tag: string, _properties: Record<string, unknown>) => import("lit-html/directive.js").DirectiveResult<typeof CustomElementDirective>;
export {};
