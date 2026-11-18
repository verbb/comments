import { type TemplateResult } from 'lit-html';
/**
 * One line-icon style throughout, matching Editor's. Each icon is sized and drawn by its own
 * attributes too, so a site's CSS, or theme None, starts from a sensible icon. The paths are
 * `svg` templates, because lit-html parses an `html` one as HTML even inside an `<svg>`.
 */
declare const paths: {
    readonly heart: TemplateResult<2>;
    readonly up: TemplateResult<2>;
    readonly down: TemplateResult<2>;
    readonly reply: TemplateResult<2>;
    readonly more: TemplateResult<2>;
    readonly bell: TemplateResult<2>;
    readonly user: TemplateResult<2>;
    readonly clock: TemplateResult<2>;
    readonly spam: TemplateResult<2>;
    readonly flag: TemplateResult<2>;
    readonly removed: TemplateResult<2>;
    readonly close: TemplateResult<2>;
    readonly check: TemplateResult<2>;
    readonly alert: TemplateResult<2>;
    readonly info: TemplateResult<2>;
    readonly file: TemplateResult<2>;
    readonly attach: TemplateResult<2>;
    readonly retry: TemplateResult<2>;
};
export type IconName = keyof typeof paths;
/** An icon in the thread, `comments__icon comments__icon--{name}`. */
export declare function icon(name: IconName): TemplateResult;
/** An icon in the comment editor's frame, drawn as Editor draws its own. */
export declare function frameIcon(name: IconName, extra?: string): TemplateResult;
export {};
