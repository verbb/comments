import type { ThemeConfig, ThreadState } from '@verbb/comments-core';
import { type FormMode, type FormView } from './parts/form.js';
import { type FieldOverride, type PartName, type PartOverride, type ThreadPhase, type ThreadView, type UiState } from './view.js';
export type StringViewOptions = {
    state: ThreadState;
    instanceId?: string;
    phase?: ThreadPhase;
    count?: number;
    ui?: Partial<UiState>;
    labels?: Record<string, string>;
    themeConfig?: ThemeConfig;
    /** Function overrides only: a custom element's name draws as an empty element. */
    parts?: Partial<Record<PartName, PartOverride>>;
    /** Custom field overrides by handle, as `Comments.configure({ fields })` takes them. */
    fields?: Record<string, FieldOverride>;
};
/**
 * A view of a thread's state for drawing to a string, with the comment forms drawn from their
 * templates rather than the live elements the browser uses.
 */
export declare function createStringView(options: StringViewOptions): ThreadView;
/** A comment form's view, as drawn to a string, for one of a thread view's forms. */
export declare function createFormView(thread: ThreadView, mode: FormMode, uid: string | null): FormView;
