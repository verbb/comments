/** Preserve Craft's query-string route when pretty URLs are disabled. */
export function frontendApiUrl(base: string, path: string): URL {
    const url = new URL(base);
    if (url.searchParams.has('p')) {
        url.searchParams.set('p', `${url.searchParams.get('p')!.replace(/\/$/, '')}/${path}`);
    } else {
        url.pathname = `${url.pathname.replace(/\/$/, '')}/${path}`;
    }
    return url;
}
