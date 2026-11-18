export { COMMENTS_THREAD_TAG, CommentsThreadElement } from './element.js';
export type { CommentsController, CommentsEventMap, CommentsEventName, CommentsTheme, ComponentOverride, FieldOverride, LoadMode, PartContext, PartName, PartOverride, ThemeConfig, } from '@verbb/comments-browser';
/**
 * Defines `<verbb-comments>`. Safe to call more than once, and does nothing without custom elements,
 * such as in a server render. If another copy of this package defined the tag first, that copy's
 * element draws the threads, and this says so in the console.
 */
export declare function registerCommentsWebComponents(): void;
