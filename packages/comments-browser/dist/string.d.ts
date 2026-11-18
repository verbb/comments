/**
 * Renders a part's template to an HTML string without a DOM, for tests, the markup reference and
 * server-side rendering. It covers what Comments' own templates use: text and nested templates,
 * whole-value attribute bindings (`name=${value}`, with `?` for booleans), interpolation inside
 * quoted attributes, and Comments' own directives. Property (`.`) and event (`@`) bindings exist
 * only in the browser, so they're left out.
 */
export declare function renderToString(value: unknown): string;
export declare function escapeHtml(value: string): string;
