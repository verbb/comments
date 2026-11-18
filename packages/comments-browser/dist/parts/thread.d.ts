import { type TemplateResult } from 'lit-html';
import type { ThreadView } from '../view.js';
/**
 * The whole thread: the live region, header, the editor or a way in, the list, the toast and the
 * report dialog. Before the thread loads, the list is a loading line or the Show comments button.
 */
export declare function threadTemplate(view: ThreadView): TemplateResult;
/**
 * Stands in for a member's collapsed editor until the form chunk arrives, drawn the same size, so
 * the list below doesn't move. Focusing it starts the load, and the real box takes the focus. Its box
 * sits in a `comments__frame-box`, as the real form's does, because a collapsed frame shows nothing else.
 */
export declare function formPlaceholder(view: ThreadView, failed?: boolean): TemplateResult;
