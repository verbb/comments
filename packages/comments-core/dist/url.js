const ABSOLUTE = /^[a-z][a-z\d+.-]*:\/\//i;
const PLACEHOLDER_ORIGIN = 'http://comments.invalid';
/**
 * An action under the API's base URL, such as `https://example.com/actions/comments/api` plus
 * `thread`. Keeps Craft's query-string route (`index.php?p=actions/comments/api`) when pretty URLs
 * are off, keeps a relative base relative, and adds the query parameters in the order given, with
 * lists as `name[]`. Empty values are left out.
 */
export function apiUrl(endpoint, action, query = {}) {
    const base = endpoint.trim() || '/';
    const url = new URL(base, PLACEHOLDER_ORIGIN);
    const route = url.searchParams.get('p');
    if (route !== null) {
        url.searchParams.set('p', `${route.replace(/\/+$/, '')}/${action}`);
    }
    else {
        url.pathname = `${url.pathname.replace(/\/+$/, '')}/${action}`;
    }
    for (const [name, value] of Object.entries(query)) {
        if (value === undefined || value === null || value === '') {
            continue;
        }
        if (Array.isArray(value)) {
            for (const item of value) {
                url.searchParams.append(`${name}[]`, String(item));
            }
        }
        else {
            url.searchParams.set(name, String(value));
        }
    }
    if (ABSOLUTE.test(base)) {
        return url.toString();
    }
    return `${base.startsWith('//') ? `//${url.host}` : ''}${url.pathname}${url.search}`;
}
