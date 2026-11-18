import type { CommentsApp as CommentsAppType } from './types.js';
import type { CommentsEventMap as CommentsEventMapType } from './events.js';
export { Comments, configure, mountThread, setTheme, startComments } from './start.js';
export type { StartOptions } from './start.js';
export type { CommentsEditorMode, CommentsEventDetail, CommentsEventDetailMap, CommentsEventMap, CommentsEventName } from './events.js';
export { CONTROL_NAMES, CONTROL_STYLE_CLASSES, mergeThemeConfig } from './theme-config.js';
export type { ControlSpec, ResolvedThemeConfig } from './theme-config.js';
export { PART_NAMES } from './view.js';
export type { CommentActions, ComponentHost, ComponentHostKind, ComponentOverride, FieldDisplayContext, FieldInputContext, HostContext, PartContext, PartName, PartOverride } from './view.js';
export { FALLBACK_LABELS } from './labels.js';
export { TRUSTED_TYPES_POLICY } from './trusted.js';
export type { CommentsApp, CommentsController, CommentsFormErrors, CommentsSettings, CommentsTheme, FieldOverride, LoadMode, MountOptions, ThreadOptions, } from './types.js';
export type { ThemeConfig, ThemeControlConfig, ThemeControlName } from '@verbb/comments-core';
declare global {
    interface Window {
        Comments?: CommentsAppType;
    }
    interface HTMLElementEventMap extends CommentsEventMapType {
    }
    interface DocumentEventMap extends CommentsEventMapType {
    }
    interface WindowEventMap extends CommentsEventMapType {
    }
}
