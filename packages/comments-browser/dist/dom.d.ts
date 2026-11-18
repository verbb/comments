/**
 * Small page helpers shared by the thread and its forms.
 */
export declare function readDraft(key: string): {
    savedAt: number;
    text: string;
} | null;
export declare function writeDraft(key: string, text: string | null): void;
/** Whether Editor kept a draft under this key, which only Editor itself can bring back. */
export declare function hasEditorDraft(key: string): boolean;
export declare function isMac(): boolean;
/** "5 minutes ago", in the page's language, for a draft's age. */
export declare function relativeTime(timestamp: number): string;
export declare function prefersReducedMotion(): boolean;
/** Something visible, so focus doesn't land on a hidden element. */
export declare function isShown(element: Element): boolean;
/**
 * Imports a chunk again after it failed to load, for someone pressing **Try again**. Chromium keeps
 * a failed module fetch for the life of the page (whatwg/html#6768), so importing it again fails
 * without asking the server. When it fails again and the server now answers for the chunk, only a
 * new page can load it, so the page reloads. That's also what fixes a tab left open across an
 * update, whose chunks have gone. Without an answer, such as when offline, it fails as before.
 * The startup loader keeps its own copy, as nothing the renderer imports may live in it.
 */
export declare function importAgain<T>(load: () => Promise<T>): Promise<T>;
/**
 * Like `importAgain()`, without reloading the page: when importing again fails, the chunk is
 * imported from its address with a query added, which Chromium hasn't seen fail. Only for a chunk
 * no other chunk imports, such as the file tray, as one importing it by its own address would
 * still fail, and for one whose page has something worth keeping, such as a comment being written.
 */
export declare function importFresh<T>(load: () => Promise<T>): Promise<T>;
