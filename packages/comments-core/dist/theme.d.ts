/**
 * The ten controls `themeConfig` reaches, which design systems standardise. The browser package
 * holds the list itself, to warn about a name it doesn't know.
 */
export type ThemeControlName = 'primaryButton' | 'secondaryButton' | 'quietButton' | 'input' | 'textarea' | 'select' | 'checkbox' | 'radio' | 'link' | 'avatar';
export type ThemeControlConfig = {
    /** Drops Comments' styling classes on the control before `attributes.class` is added. */
    resetClass?: boolean;
    /** Merged onto every element of the control. `class` is added to Comments' own classes. */
    attributes?: Record<string, string | string[] | number | boolean | null>;
};
export type ThemeConfig = Partial<Record<ThemeControlName, ThemeControlConfig>>;
/** The parts a site can override, each one public API. */
export type PartName = 'header' | 'empty' | 'showButton' | 'loading' | 'notice' | 'comment' | 'avatar' | 'authorName' | 'badge' | 'meta' | 'content' | 'fields' | 'actions' | 'review' | 'placeholder' | 'join' | 'postingAs' | 'formFields';
