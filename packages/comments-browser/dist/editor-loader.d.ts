import type { GifData, RichEditorData } from '@verbb/comments-core';
/** The parts of Editor's `<verbb-editor>` element Comments uses. */
export interface VerbbEditorElement extends HTMLElement {
    readonly editor: {
        getText(): string;
        getJSON(): Record<string, unknown>;
        setContent(content: unknown): void;
        isUploading(): boolean;
        isEmpty?(): boolean;
        focus?(): void;
        /** Editor's GIF control, when the options give it GIPHY's key. */
        getGif?(): GifData | null;
        setGif?(gif: GifData | null): void;
        reportGifSent?(): void;
    } | null;
    options: Record<string, unknown>;
    showPostError(message?: string): void;
    clearPostError(): void;
    clearDraft(): void;
}
export declare function editorDefined(): boolean;
/**
 * Loads Editor's bundle: a site's own loader if it gave one (an import from npm, say), else
 * Editor's own module from the URL in the thread's data, with the page's nonce, but only from the
 * data API's origin or this script's own. With neither, it waits a while for a bundle the page
 * loads itself. It never runs until someone heads for a rich editor, so Editor is never in the
 * page's first download, and it gives up after a while if Editor never defines its element.
 */
export declare function loadEditor(source: RichEditorData | null, options?: {
    loader?: () => Promise<unknown>;
    nonce?: string;
}): Promise<void>;
/**
 * Loads the Editor features these options use, such as links and mentions. Editor's own module
 * loads them on demand, so loading them before adding `<verbb-editor>` means it mounts the moment
 * it's added. An Editor without on-demand features has them already.
 */
export declare function loadEditorFeatures(options: Record<string, unknown>): Promise<void>;
