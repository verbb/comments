import { type TemplateResult } from 'lit-html';
import type { ThreadView } from '../view.js';
/**
 * The report dialog. It's one `<dialog>` for the thread, opened with `showModal()` for whichever
 * comment's Report… was chosen, and its form can't be overridden, because it carries the reasons
 * the server accepts.
 */
export declare function reportDialog(view: ThreadView): TemplateResult;
