import type { FieldDisplayContext, FieldInputContext, PartContext, PartName } from '@verbb/comments-browser';
import type { Component } from 'vue';

const TEMPLATE = Symbol.for('verbb.comments.templatePart');

type HostExtras = {
    /**
     * Draws Comments' own markup for this part into an element the component owns, to wrap or fall
     * back to. Call it after each render, such as from `onMounted()` and `onUpdated()`.
     */
    renderDefaultInto(into: Element): void;
};

/**
 * What a part override component is given as props: the part's data (`comment`, `viewer`,
 * `permissions`, `conversation` and the like), `labels`, `actions` bound to the comment,
 * `controlAttributes()` and `renderDefaultInto()`. Declare the ones it uses with `defineProps()`.
 */
export type CommentsPartProps = PartContext & HostExtras;
/** What a custom field's input override component is given: `field`, `value`, `setValue()`, `error`, `id`, `labelId`, `describedBy` and the rest. */
export type CommentsFieldInputProps = FieldInputContext & HostExtras;
/** What a custom field's display override component is given: `field`, `comment`, `value` and `text`. */
export type CommentsFieldDisplayProps = FieldDisplayContext & HostExtras;

/** A renderer function wrapped by `templatePart()`, passed to the renderer as it is. */
export type TemplatePart<Context> = {
    readonly [TEMPLATE]: (context: Context) => unknown;
};

/**
 * A part override: a Vue component, a `templatePart()` function for cheap static markup, or a
 * registered custom element's name.
 */
export type CommentsPartOverride = Component | TemplatePart<PartContext> | string;

export type CommentsPartOverrides = Partial<Record<PartName, CommentsPartOverride>>;

/** One custom field's overrides by handle, each a component, a `templatePart()` function or a custom element's name. */
export type CommentsFieldOverride = {
    input?: Component | TemplatePart<FieldInputContext> | string;
    display?: Component | TemplatePart<FieldDisplayContext> | string;
};

export type CommentsFieldOverrides = Record<string, CommentsFieldOverride>;

/**
 * Marks a function as a renderer override rather than a component: it's given the part's context,
 * with `html` and `defaultRender()`, and returns a template, a string or a node, which the renderer
 * draws itself. Cheaper than a component for static markup.
 *
 * ```js
 * badge: templatePart(({ badge, html, defaultRender }) => badge.type === 'verified' ? html`<span class="badge">✓</span>` : defaultRender())
 * ```
 */
export function templatePart<Context = PartContext>(render: (context: Context) => unknown): TemplatePart<Context> {
    return Object.freeze({ [TEMPLATE]: render });
}

export function isTemplatePart(value: unknown): value is TemplatePart<never> {
    return Boolean(value) && typeof value === 'object' && typeof (value as Record<symbol, unknown>)[TEMPLATE] === 'function';
}

export function templateOf<Context>(value: TemplatePart<Context>): (context: Context) => unknown {
    return value[TEMPLATE];
}

/** A Vue component: an options object (as `defineComponent()` and single-file components make) or a functional component. */
export function isComponent(value: unknown): value is Component {
    return typeof value === 'function' || (Boolean(value) && typeof value === 'object');
}
