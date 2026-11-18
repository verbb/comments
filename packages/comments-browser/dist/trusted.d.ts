export declare const TRUSTED_TYPES_POLICY = "verbb-comments";
/** Lets Editor's module come from this URL's origin, such as the data API's. */
export declare function allowScriptOrigin(url: string): void;
/** Whether a script URL is on an origin the thread trusts. */
export declare function scriptAllowed(url: string): boolean;
/** A comment's sanitised HTML, ready for `innerHTML`. */
export declare function trustedHtml(html: string): string;
/** Editor's module URL, ready for a script's `src`. Refused unless its origin is allowed. */
export declare function trustedScriptUrl(url: string): string;
