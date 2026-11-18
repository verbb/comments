import { createContext, createElement, useContext, useMemo } from 'react';
const CommentsContext = createContext({});
/** Gives every `<CommentsThread>` and hook inside it the same settings. A thread's own props win. */
export function CommentsProvider({ children, endpoint, auth, siteId, themeConfig, parts, fields, labels, theme, darkSelector, loadEditor, nonce, fetch }) {
    const parent = useContext(CommentsContext);
    const value = useMemo(() => ({
        ...parent,
        ...defined({ endpoint, auth, siteId, themeConfig, parts, fields, labels, theme, darkSelector, loadEditor, nonce, fetch }),
    }), [parent, endpoint, auth, siteId, themeConfig, parts, fields, labels, theme, darkSelector, loadEditor, nonce, fetch]);
    return createElement(CommentsContext.Provider, { value }, children);
}
/** The settings from the nearest `<CommentsProvider>`, or none. */
export function useCommentsSettings() {
    return useContext(CommentsContext);
}
/**
 * Editor from npm, split by the app's bundler into a chunk of its own. Importing it registers
 * nothing, so its `<verbb-editor>` element is registered here before the form swaps it in.
 */
export const loadEditorFromPackage = async () => {
    const editor = await import('@verbb/editor');
    editor.registerVerbbEditorElement();
};
function defined(values) {
    return Object.fromEntries(Object.entries(values).filter(([, value]) => value !== undefined));
}
