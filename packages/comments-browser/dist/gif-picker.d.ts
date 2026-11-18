import type { GifsData } from '@verbb/comments-core';
import { GifSearch, type GifChoice } from '@verbb/editor/gifs';
import { type TemplateResult } from 'lit-html';
import type { FormView } from './parts/form.js';
/**
 * GIF search through GIPHY for the plain form. It's its own chunk, loaded when someone first opens
 * the picker, so a thread that never opens it never downloads this or talks to GIPHY.
 *
 * The search itself is Editor's (`@verbb/editor/gifs`), the same one Editor's GIF control uses when
 * rich authoring is on: it talks to GIPHY from the browser as GIPHY's terms require, and sends
 * GIPHY's pings. This draws its results in Comments' own markup, with Comments' classes.
 */
export { chosen, Giphy, thumbnail } from '@verbb/editor/gifs';
export type { GiphyResult } from '@verbb/editor/gifs';
/** A chosen GIF, and the ping GIPHY wants once the comment is posted. */
export type ChosenGif = GifChoice;
export type PickerStatus = GifSearch['status'];
/**
 * One form's picker: Editor's search, and the panel drawn from it. The form draws the panel inside
 * itself and calls `render` again whenever the picker changes.
 */
export declare class GifPicker extends GifSearch {
    /** The panel, drawn for a form. */
    panel(view: FormView): TemplateResult;
}
/** What the panel is drawn from. */
export type PickerView = Pick<GifPicker, 'query' | 'status' | 'results'>;
/** What the picker needs from the form it opens in. */
export type PickerForm = {
    element: HTMLFormElement;
    signal: AbortSignal;
    isOpen(): boolean;
    render(): void;
    chosen(choice: ChosenGif): void;
    close(): void;
};
/**
 * Sets the picker up in a form: its search, its keys and its results, so the form's own chunk only
 * opens and closes it.
 */
export declare function attachGifPicker(config: GifsData, form: PickerForm): GifPicker;
/**
 * The picker's panel: a search box, GIPHY's results as buttons, what's happening, and the "Powered by
 * GIPHY" mark GIPHY's terms ask for wherever its search is used.
 */
export declare function gifPickerTemplate(view: FormView, picker: PickerView): TemplateResult;
