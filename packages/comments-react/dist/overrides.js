const TEMPLATE = Symbol.for('verbb.comments.templatePart');
/**
 * Marks a function as a renderer override rather than a component: it's given the part's context,
 * with `html` and `defaultRender()`, and returns a template, a string or a node, which the renderer
 * draws itself. Cheaper than a component for static markup.
 *
 * ```js
 * badge: templatePart(({ badge, html, defaultRender }) => badge.type === 'verified' ? html`<span class="badge">✓</span>` : defaultRender())
 * ```
 */
export function templatePart(render) {
    return Object.freeze({ [TEMPLATE]: render });
}
export function isTemplatePart(value) {
    return Boolean(value) && typeof value === 'object' && typeof value[TEMPLATE] === 'function';
}
export function templateOf(value) {
    return value[TEMPLATE];
}
/** A React component: a function, a class, or one of React's wrapped kinds such as `memo()` and `lazy()`. */
export function isComponent(value) {
    return typeof value === 'function' || (Boolean(value) && typeof value === 'object' && '$$typeof' in value);
}
