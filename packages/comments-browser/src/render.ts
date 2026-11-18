/**
 * The renderer's parts without a page: the same templates the browser draws, as functions of plain
 * data, and `renderToString()` to turn them into HTML, for the package's own tests and the
 * generated markup reference. It isn't one of the package's entry points, so none of it is public.
 * Nothing here touches the DOM until a template is drawn into one.
 */
export { renderToString, escapeHtml } from './string.js';
export { threadTemplate } from './parts/thread.js';
export { commentItem, badgeTemplate, avatar, MAX_VISUAL_DEPTH } from './parts/comment.js';
export { reportDialog } from './parts/report.js';
export { formTemplate, guestFieldsTemplate, postingAsTemplate, formFieldsTemplate } from './parts/form.js';
export { fileTray, UPLOAD_LABELS } from './parts/tray.js';
export { gifAreaTemplate, gifButtonAttributes, gifButtonContent } from './parts/gif.js';
export { gifPickerTemplate } from './gif-picker.js';
export type { TrayParts, UploadItem } from './parts/tray.js';
export { safeUrl } from './parts/fields.js';
export type { FormMode, FormView } from './parts/form.js';
export { icon, frameIcon } from './parts/icons.js';
export type { IconName } from './parts/icons.js';
export { createFormView, createStringView } from './string-view.js';
export type { StringViewOptions } from './string-view.js';
export { emptyUi, anchorOf, PART_NAMES } from './view.js';
export type { ThreadPhase, ThreadView, UiState } from './view.js';
export { resolveLabels, format, FALLBACK_LABELS } from './labels.js';
export { controlAttributes, mergeThemeConfig, CONTROL_NAMES, CONTROL_STYLE_CLASSES } from './theme-config.js';
export { STABLE_CLASSES, INTERNAL_CLASSES, PUBLIC_ATTRIBUTES, MOUNT_POINT_ATTRIBUTES, HOST_ATTRIBUTES, classMatches } from './classes.js';
