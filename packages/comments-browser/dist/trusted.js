export const TRUSTED_TYPES_POLICY = 'verbb-comments';
// Kept in this module, never on a global another script could reach. A second copy of the package
// can't create the same name again unless the site allows duplicates, so that copy's comment
// bodies and Editor are refused, as any value without a policy is.
let created;
// The origins Editor's module may come from: each thread's data API and this script's own.
const scriptOrigins = new Set();
/** Lets Editor's module come from this URL's origin, such as the data API's. */
export function allowScriptOrigin(url) {
    try {
        scriptOrigins.add(new URL(url, globalThis.location?.href).origin);
    }
    catch {
        // Not a URL, so nothing is allowed.
    }
}
/** Whether a script URL is on an origin the thread trusts. */
export function scriptAllowed(url) {
    try {
        const parsed = new URL(url, globalThis.location?.href);
        return (parsed.protocol === 'https:' || parsed.protocol === 'http:') && scriptOrigins.has(parsed.origin);
    }
    catch {
        return false;
    }
}
function policy() {
    const page = globalThis;
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
        }
        catch {
            // The site's policy doesn't allow the name, so the browser refuses the plain string
            // instead, and the comment body or Editor fails the way any refused value does.
            created = null;
        }
    }
    return created;
}
/** A comment's sanitised HTML, ready for `innerHTML`. */
export function trustedHtml(html) {
    const current = policy();
    return (current ? current.createHTML(html) : html);
}
/** Editor's module URL, ready for a script's `src`. Refused unless its origin is allowed. */
export function trustedScriptUrl(url) {
    if (!scriptAllowed(url)) {
        throw new TypeError('Comments only loads Editor from the data API’s origin or its own.');
    }
    const current = policy();
    return (current ? current.createScriptURL(url) : url);
}
