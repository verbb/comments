import { CommentsRequestError, GENERIC_MESSAGE, normalizeErrorData } from './errors.js';
import { createOperationId } from './operation.js';
import { abortable, requestSignal } from './signal.js';
import { COMMENTS_PROFILE_HEADER, cookieSession } from './sign-in.js';
import { restTransport } from './transport.js';
import { COMMENTS_API_VERSION, COMMENTS_API_VERSION_HEADER, assertApiVersion } from './version.js';
/**
 * The data API client. Public reads go without credentials or sign-in headers, so any cache can
 * hold them; everything else goes through the sign-in provider. Every write gets an
 * `operationId`, and one that loses its connection is sent once more with the same ID, which the
 * server answers from its replay store rather than acting twice.
 */
export function createCommentsClient(options) {
    const auth = options.auth ?? defaultSignIn(options.profile);
    const rest = restTransport({ endpoint: options.endpoint, fetch: options.fetch });
    const transport = options.transport ?? rest;
    if (typeof transport.send !== 'function') {
        throw new Error('Comments: `transport` takes a transport, such as graphqlTransport() from @verbb/comments-core/graphql.');
    }
    const siteId = options.siteId ?? null;
    const timeoutMs = options.timeoutMs ?? 30000;
    const client = {
        endpoint: options.endpoint,
        siteId,
        auth,
        transport,
        thread: (params, request) => send({ kind: 'public', action: 'thread', query: { ...threadQuery(params), v: params.threadVersion } }, request),
        replies: (params, request) => send({ kind: 'public', action: 'replies', query: { parentUid: params.parentUid, cursor: params.cursor, limit: params.limit } }, request),
        comment: (target, request) => send({ kind: 'public', action: 'comment', query: typeof target === 'string' ? { uid: target } : target }, request),
        viewer: (params, request) => send({ kind: 'private', action: 'viewer', query: { ...threadQuery(params), uids: params.uids?.length ? params.uids : undefined, ids: params.ids?.length ? params.ids : undefined } }, request),
        session: (request) => send({ kind: 'private', action: 'session' }, request),
        protection: (params, request) => send({
            kind: 'private',
            action: 'protection',
            query: { ownerId: params.ownerId, siteId: params.siteId ?? siteId, commentUid: params.commentUid, channel: transport.protectionChannel },
        }, request),
        structuredData: (params, request) => send({
            kind: 'public',
            action: 'structured-data',
            query: { ownerId: params.ownerId, ownerUri: params.ownerUri, siteId: params.siteId ?? siteId, limit: params.limit },
        }, request),
        edit: (uid, request) => send({ kind: 'private', action: 'edit', query: { uid } }, request),
        create: (input, request) => write('create', { ...input, siteId: input.siteId ?? siteId, parentUid: input.parentUid ?? null }, request),
        update: (input, request) => write('update', input, request),
        delete: (input, request) => write('delete', input, request),
        vote: (input, request) => write('vote', input, request),
        report: (input, request) => write('report', input, request),
        follow: (input, request) => write('follow', { ...input, siteId: input.siteId ?? siteId }, request),
        moderate: (input, request) => write('moderate', input, request),
        // Editor's `scope` is an object, which the endpoint takes as a JSON string.
        mentions: (params, request) => send({
            kind: 'private',
            action: 'mentions',
            query: Object.fromEntries(Object.entries(params).map(([name, value]) => [name, isPlainObject(value) ? JSON.stringify(value) : value])),
            editorFormat: true,
        }, request),
        upload: (input, request) => {
            const body = new FormData();
            const { file, filename, params, protection, ...fields } = input;
            for (const [name, value] of Object.entries({ ...params, ...fields, protection: protection && JSON.stringify(protection) })) {
                if (value !== undefined && value !== null)
                    body.append(name, String(value));
            }
            body.append('file', file, filename ?? (file.name || 'upload'));
            return send({ kind: 'write', action: 'upload', body, editorFormat: true }, request);
        },
    };
    function threadQuery(params) {
        return {
            ownerId: params.ownerId,
            ownerUri: params.ownerUri,
            siteId: params.siteId ?? siteId,
            sort: params.sort,
            limit: params.limit,
            cursor: params.cursor,
        };
    }
    function write(action, input, request = {}) {
        const operationId = request.operationId ?? createOperationId();
        return send({ kind: 'write', action, body: { operationId, ...input }, operationId }, request);
    }
    async function send(call, request = {}) {
        let resent = false;
        let recovered = false;
        for (;;) {
            try {
                return await attempt(call, request);
            }
            catch (error) {
                if (!(error instanceof CommentsRequestError)) {
                    throw error;
                }
                // Only a write with an `operationId` is safe to send again: the server answers a
                // repeat from its replay store. A request the caller cancelled never gets here.
                if (call.operationId && error.code === 'network_error' && !resent) {
                    resent = true;
                    continue;
                }
                if (call.kind !== 'public' && error.category === 'auth' && !recovered && auth.recover && await auth.recover(error, signInContext(call, request.signal))) {
                    recovered = true;
                    continue;
                }
                throw error;
            }
        }
    }
    async function attempt(call, request) {
        const identified = call.kind !== 'public';
        const headers = {
            Accept: 'application/json',
            ...(identified ? options.headers?.private : options.headers?.public),
            [COMMENTS_API_VERSION_HEADER]: String(COMMENTS_API_VERSION),
        };
        const timer = requestSignal(request.signal, timeoutMs);
        let response;
        try {
            // Inside the timeout, since a provider may make a request of its own first.
            if (identified) {
                Object.assign(headers, await abortable(auth.headers(signInContext(call, timer.signal)), timer.signal), { [COMMENTS_PROFILE_HEADER]: auth.profile });
            }
            const outgoing = {
                kind: call.kind === 'write' ? 'write' : 'read',
                action: call.action,
                query: call.query,
                body: call.body,
                headers,
                credentials: identified ? auth.credentials : 'omit',
                redirect: identified ? 'manual' : 'follow',
                cache: request.cache,
                signal: timer.signal,
            };
            response = await (transport.handles?.(outgoing) === false ? rest : transport).send(outgoing);
        }
        catch (error) {
            // The caller's own cancellation isn't a failure to report or retry.
            if (request.signal?.aborted) {
                throw error;
            }
            if (error instanceof CommentsRequestError) {
                throw error.withOperation(call.operationId);
            }
            if (timer.timedOut() || isConnectionFailure(error)) {
                const message = timer.timedOut() ? 'The request took too long. Please try again.' : 'Couldn’t reach the server. Check your connection and try again.';
                throw CommentsRequestError.from('network_error', message, { operationId: call.operationId ?? null, recoverable: true, cause: error });
            }
            // A mistake in code, such as a custom transport's, isn't worth sending again.
            throw CommentsRequestError.from('invalid_request', GENERIC_MESSAGE, {
                operationId: call.operationId ?? null,
                cause: error,
                detail: `not sent: ${String(error)}`,
            });
        }
        finally {
            timer.cleanup();
        }
        const payload = response.payload;
        const version = response.headers.get(COMMENTS_API_VERSION_HEADER);
        // An error answer is read before the version check, so the server's own
        // `unsupported_api_version` reaches the caller as itself.
        if (payload && payload.status === 'error' && Array.isArray(payload.errors)) {
            assertApiVersion(payload, version, false);
            const errors = payload.errors.map(normalizeErrorData);
            const retryAfter = retryAfterHeader(response.headers.get('Retry-After'));
            if (errors[0] && errors[0].retryAfter === null && retryAfter !== null) {
                errors[0] = { ...errors[0], retryAfter };
            }
            throw new CommentsRequestError(errors, { status: response.status, operationId: call.operationId ?? null, detail: response.detail ?? null });
        }
        try {
            assertApiVersion(payload, version, !call.editorFormat);
        }
        catch (error) {
            throw error.withOperation(call.operationId);
        }
        if (response.status >= 400) {
            throw CommentsRequestError.from(response.status >= 500 ? 'server_error' : 'invalid_response', GENERIC_MESSAGE, {
                status: response.status,
                operationId: call.operationId ?? null,
                recoverable: response.status >= 500,
                detail: `HTTP ${response.status}`,
            });
        }
        if (identified) {
            auth.observe?.(payload, response.headers);
        }
        return payload;
    }
    function signInContext(call, signal) {
        // The `session` read gets its own timeout rather than this request's signal, so several
        // writes can share it.
        return { write: call.kind === 'write', endpoint: options.endpoint, session: () => client.session(), signal };
    }
    return client;
}
function defaultSignIn(profile) {
    if (profile === 'cross-origin-member' || profile === 'cross-origin-public') {
        throw new Error(`Comments: the ${profile} profile needs a sign-in provider passed as \`auth\`.`);
    }
    return cookieSession({ profile });
}
function isPlainObject(value) {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}
/** Whether fetch failed to reach the server, rather than code going wrong around it. */
function isConnectionFailure(error) {
    const name = error?.name;
    return error instanceof TypeError || name === 'AbortError' || name === 'TimeoutError' || name === 'NetworkError';
}
/** `Retry-After` in seconds, from a number of seconds or an HTTP date. */
function retryAfterHeader(value) {
    if (!value)
        return null;
    const seconds = Number(value);
    if (Number.isFinite(seconds))
        return Math.max(0, Math.ceil(seconds));
    const date = Date.parse(value);
    return Number.isNaN(date) ? null : Math.max(0, Math.ceil((date - Date.now()) / 1000));
}
