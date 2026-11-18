import { computed, inject, provide, toValue } from 'vue';
export const COMMENTS_SETTINGS = Symbol('verbb.comments.settings');
/**
 * `app.use(CommentsPlugin, { endpoint, auth, themeConfig, parts })` gives every thread and
 * composable the same settings. Pass a `reactive()` object, a ref or a getter to change them later,
 * such as a new `auth` when someone signs in.
 */
export const CommentsPlugin = {
    install(app, settings = {}) {
        app.provide(COMMENTS_SETTINGS, live(() => toValue(settings)));
    },
};
/**
 * Settings for the components below this one, over the app's. A thread's own props still win. As
 * with the plugin, a `reactive()` object, a ref or a getter keeps them up to date.
 */
export function provideCommentsSettings(settings) {
    const parent = useCommentsSettings();
    provide(COMMENTS_SETTINGS, live(() => ({ ...parent, ...toValue(settings) })));
}
/**
 * Settings that are read afresh, and tracked, wherever they're used, so a thread's computed values
 * follow a change. Not deeply reactive, so components and sign-in providers stay as they were given.
 */
function live(source) {
    const value = computed(source);
    return new Proxy({}, {
        get: (_target, key) => value.value[key],
        has: (_target, key) => key in value.value,
        ownKeys: () => Reflect.ownKeys(value.value),
        getOwnPropertyDescriptor: (_target, key) => (key in value.value ? { value: value.value[key], enumerable: true, configurable: true } : undefined),
    });
}
/** The settings from `CommentsPlugin` or `provideCommentsSettings()`, or none. */
export function useCommentsSettings() {
    return inject(COMMENTS_SETTINGS, {});
}
/**
 * Editor from npm, split by the app's bundler into a chunk of its own. Importing it registers
 * nothing, so its `<verbb-editor>` element is registered here before the form swaps it in.
 */
export const loadEditorFromPackage = async () => {
    const editor = await import('@verbb/editor');
    editor.registerVerbbEditorElement();
};
