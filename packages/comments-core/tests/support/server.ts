import { vi } from 'vitest';

export const ENDPOINT = 'https://example.com/actions/comments/api';
export const GRAPHQL_ENDPOINT = 'https://example.com/api';

export type RecordedRequest = {
    url: URL;
    /** The REST action, or the GraphQL operation's first field. */
    action: string;
    method: string;
    headers: Headers;
    credentials: RequestCredentials | undefined;
    redirect: RequestRedirect | undefined;
    cache: RequestCache | undefined;
    body: any;
    signal: AbortSignal | undefined;
};

type Responder = (request: RecordedRequest) => Response | Promise<Response>;

/** A JSON answer with the version header the server echoes. */
export function json(payload: unknown, status = 200, headers: Record<string, string> = {}): Response {
    return new Response(JSON.stringify(payload), {
        status,
        headers: { 'Content-Type': 'application/json', 'X-Comments-Api-Version': '1', ...headers },
    });
}

/**
 * A fake data API behind a mocked fetch. `on(action, ...responders)` answers in turn and repeats
 * the last; GraphQL requests are routed by their first field, such as `commentsThread`.
 */
export function mockServer() {
    const requests: RecordedRequest[] = [];
    const routes = new Map<string, Responder[]>();

    const fetch = vi.fn(async (input: RequestInfo | URL, init: RequestInit = {}) => {
        const url = new URL(String(input));
        const rawBody = init.body;
        let body: unknown = rawBody;
        let action = url.searchParams.get('p')?.split('/').pop() ?? url.pathname.split('/').pop() ?? '';

        if (typeof rawBody === 'string') {
            body = JSON.parse(rawBody);
        }

        if (url.pathname === '/api' && body && typeof body === 'object' && 'query' in body) {
            action = /\{\s*(\w+)/.exec(String((body as { query: string }).query))?.[1] ?? '';
        }

        const request: RecordedRequest = { url, action, method: init.method ?? 'GET', headers: new Headers(init.headers), credentials: init.credentials, redirect: init.redirect, cache: init.cache, body, signal: init.signal ?? undefined };
        requests.push(request);

        const queue = routes.get(action);

        if (!queue?.length) {
            throw new Error(`The mock server has no answer for ${action}.`);
        }

        const responder = queue.length > 1 ? queue.shift()! : queue[0]!;
        return responder(request);
    });

    return {
        fetch: fetch as unknown as typeof globalThis.fetch,
        requests,
        on(action: string, ...responders: Array<Responder | unknown>) {
            routes.set(action, responders.map((responder) => (typeof responder === 'function' ? responder as Responder : () => json(responder))));
            return this;
        },
        /** Requests for one action. */
        sent(action: string) {
            return requests.filter((request) => request.action === action);
        },
    };
}
