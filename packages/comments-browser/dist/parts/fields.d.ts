import type { CommentData, FieldValueData } from '@verbb/comments-core';
import type { ThreadView } from '../view.js';
/** As many characters of an inline value as fit after the date, as the Twig meta line cut them. */
export declare const INLINE_FIELD_LENGTH = 40;
/**
 * An address the renderer may link to or load: `http` or `https`, or a path on this site, which
 * must stay on this site once the browser has read it (`/\t/evil.test` is `//evil.test`). The
 * server already nulls anything else, but every address is checked again here, whatever a field
 * type says, so a `javascript:` or `data:` address is never linked.
 */
export declare function safeUrl(value: unknown): string | null;
/** A shown value as text, a link for a web address, or files. Never HTML. */
export declare function fieldValueTemplate(field: FieldValueData, length?: number): unknown;
/** Values shown inline, after the date, cut to fit. */
export declare function inlineFields(view: ThreadView, comment: CommentData): unknown;
/** Values shown under the text, as a labelled list: the `fields` part. */
export declare function detailFields(view: ThreadView, comment: CommentData, data: Record<string, unknown>): unknown;
