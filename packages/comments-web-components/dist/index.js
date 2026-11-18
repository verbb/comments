import { COMMENTS_THREAD_TAG, CommentsThreadElement } from './element.js';
export { COMMENTS_THREAD_TAG, CommentsThreadElement } from './element.js';
/**
 * Defines `<verbb-comments>`. Safe to call more than once, and does nothing without custom elements,
 * such as in a server render. If another copy of this package defined the tag first, that copy's
 * element draws the threads, and this says so in the console.
 */
export function registerCommentsWebComponents() {
    if (typeof customElements === 'undefined')
        return;
    const existing = customElements.get(COMMENTS_THREAD_TAG);
    if (!existing) {
        customElements.define(COMMENTS_THREAD_TAG, CommentsThreadElement);
    }
    else if (existing !== CommentsThreadElement) {
        console.warn(`Comments: <${COMMENTS_THREAD_TAG}> is already defined by another copy of @verbb/comments-web-components, which draws these threads. Check that the page loads one copy.`);
    }
}
