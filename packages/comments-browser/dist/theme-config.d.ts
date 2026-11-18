import type { ThemeConfig, ThemeControlConfig } from '@verbb/comments-core';
import type { AttributeMap } from './directives.js';
/**
 * The ten controls `themeConfig` reaches. Design systems standardise exactly these, so a site can
 * put its own `btn btn-primary` on Comments' buttons rather than restyling ours.
 */
export declare const CONTROL_NAMES: readonly ["primaryButton", "secondaryButton", "quietButton", "input", "textarea", "select", "checkbox", "radio", "link", "avatar"];
export type ControlName = (typeof CONTROL_NAMES)[number];
/**
 * Each control's styling classes, which `resetClass` drops. The theme draws a control's look with
 * these alone (and a control's own look only where one of them is present), so a design system's
 * classes don't have to fight it once they're gone.
 */
export declare const CONTROL_STYLE_CLASSES: Record<ControlName, readonly string[]>;
/** What a part asks for when it draws a control. */
export type ControlSpec = {
    /** Classes that say what the element is, such as `comments__submit`. They carry only layout and always stay. */
    identify?: string | readonly string[];
    /** Extra styling classes, such as `comments__avatar--initials`, which `resetClass` drops with the rest. */
    style?: string | readonly string[];
    /** Attributes the script manages, such as `type`, `aria-pressed` or `data-comments-action`. */
    attributes?: Record<string, string | number | boolean | null | undefined>;
};
export type ResolvedThemeConfig = Partial<Record<ControlName, ThemeControlConfig>>;
export declare function isControlName(name: string): name is ControlName;
/**
 * Merges `themeConfig` from each source in order, later ones winning per control and per
 * attribute, and warns once about an unknown control or a protected attribute.
 */
export declare function mergeThemeConfig(sources: Array<ThemeConfig | Record<string, unknown> | null | undefined>, warn?: (message: string) => void): ResolvedThemeConfig;
/**
 * The attributes for one control: its identifying classes, its styling classes unless
 * `resetClass` is on, the site's classes and attributes, then the ones the script manages, which
 * always win. `data-comments-control` names the control for the base CSS and for tests.
 */
export declare function controlAttributes(config: ResolvedThemeConfig, name: ControlName, spec?: ControlSpec): AttributeMap;
