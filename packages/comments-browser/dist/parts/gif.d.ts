import { type TemplateResult } from 'lit-html';
import { type AttributeMap } from '../directives.js';
import type { FormView } from './form.js';
/**
 * The plain comment form's GIF button and its chosen GIF. The search itself is a chunk of its own
 * (`gif-picker.ts`), drawn into the area below once someone opens it. With rich authoring, Editor's
 * GIF control does all of this inside Editor, and the form reads the GIF from it.
 */
/** The GIF button's attributes. It sits beside Cancel in the plain form; once Editor runs, Editor's own GIF control takes over. */
export declare function gifButtonAttributes(view: FormView): AttributeMap;
export declare function gifButtonContent(view: FormView): TemplateResult;
/** The chosen GIF with a way to take it off, then the picker while it's open. */
export declare function gifAreaTemplate(view: FormView): TemplateResult;
