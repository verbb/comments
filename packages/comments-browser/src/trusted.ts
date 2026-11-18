/**
 * The `verbb-comments` Trusted Types policy. Under `require-trusted-types-for 'script'`, a site
 * allows it alongside lit-html's own (`lit-html`) and Protect's (`verbb-protect`). It creates only
 * two things: a comment's HTML, which the server has already sanitised with Editor, and the URL
 * of Editor's own module, which the thread's data names and which must be on the data API's
 * origin or this script's own. Without Trusted Types it isn't created, and both are plain strings.
 */
type TrustedPolicy = {
    createHTML(value: string): unknown;
    createScriptURL(value: string): unknown;
};

type TrustedTypesFactory = {
    createPolicy(name: string, rules: { createHTML(value: string): string; createScriptURL(value: string): string }): TrustedPolicy;
};

export const TRUSTED_TYPES_POLICY = 'verbb-comments';

// Kept in this module, never on a global another script could reach. A second copy of the package
// can't create the same name again unless the site allows duplicates, so that copy's comment
// bodies and Editor are refused, as any value without a policy is.
let created: TrustedPolicy | null | undefined;
// The origins Editor's module may come from: each thread's data API and this script's own.
const scriptOrigins = new Set<string>();

/** Lets Editor's module come from this URL's origin, such as the data API's. */
export function allowScriptOrigin(url: string): void {
    try {
        scriptOrigins.add(new URL(url, globalThis.location?.href).origin);
    } catch {
        // Not a URL, so nothing is allowed.
    }
}

/** Whether a script URL is on an origin the thread trusts. */
export function scriptAllowed(url: string): boolean {
    try {
        const parsed = new URL(url, globalThis.location?.href);
        return (parsed.protocol === 'https:' || parsed.protocol === 'http:') && scriptOrigins.has(parsed.origin);
    } catch {
        return false;
    }
}

function policy(): TrustedPolicy | null {
    const page = globalThis as { trustedTypes?: TrustedTypesFactory };

    if (!page.trustedTypes) {
        return null;
    }

    if (created === undefined) {
        try {
            created = page.trustedTypes.createPolicy(TRUSTED_TYPES_POLICY, {
                createHTML: (value) => value,
                createScriptURL(value) {
                    if (!scriptAllowed(value)) {
                        throw new TypeError('Comments only loads Editor from the data API’s origin or its own.');
                    }

                    return value;
                },
            });
        } catch {
            // The site's policy doesn't allow the name, so the browser refuses the plain string
            // instead, and the comment body or Editor fails the way any refused value does.
            created = null;
        }
    }

    return created;
}

/** A comment's sanitised HTML, ready for `innerHTML`. */
export function trustedHtml(html: string): string {
    const current = policy();
    return (current ? current.createHTML(html) : html) as string;
}

/** Editor's module URL, ready for a script's `src`. Refused unless its origin is allowed. */
export function trustedScriptUrl(url: string): string {
    if (!scriptAllowed(url)) {
        throw new TypeError('Comments only loads Editor from the data API’s origin or its own.');
    }

    const current = policy();
    return (current ? current.createScriptURL(url) : url) as string;
}
