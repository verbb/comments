import { COMMENTS_API_VERSION } from './version.js';
import { CommentsRequestError, GENERIC_MESSAGE, normalizeErrorData } from './errors.js';
import { readJson, resolveFetch, toJson } from './transport.js';
import { fieldArguments } from './graphql-literal.js';
const OWNER = { ownerId: 'ID', ownerUri: 'String', siteId: 'ID', sort: 'String' };
/**
 * Each read is the query `comments<Action>`, such as `commentsThread`, returning
 * `{ apiVersion, data }`, where `data` is the REST response without `apiVersion` and `status`,
 * built by the same serialiser.
 */
const READS = {
    thread: { ...OWNER, limit: 'Int', cursor: 'String' },
    replies: { parentUid: 'String!', cursor: 'String', limit: 'Int' },
    comment: { uid: 'String', id: 'Int' },
    viewer: { ...OWNER, cursor: 'String', limit: 'Int', uids: '[String]', ids: '[Int]' },
};
const OPERATION = { operationId: 'String', uid: 'String' };
const SAVE = { operationId: 'String', comment: 'String', contentFormat: 'String', contentDocument: 'String', protection: 'CommentsProtectionEvidence' };
/**
 * Writes go through the existing mutations, with the custom field values inlined by handle as
 * the mutations already accept them. Comment results select `commentsData`, the comment in the
 * data API's format. `deleteComment` and `subscribeComment` answer with a scalar, so their
 * responses are rebuilt from the request, with no comment count.
 */
const WRITES = {
    create: {
        field: 'createComment',
        args: { ...SAVE, ownerId: 'ID', siteId: 'ID', parentUid: 'String', name: 'String', email: 'String', notifyReplies: 'Boolean' },
        selection: '{ commentsData }',
        variables: (body) => ({ ...saveVariables(body), ...body.guest }),
        result: (value) => {
            const comment = commentsData(value);
            return { comment, moderationState: moderationState(comment), conversation: null };
        },
    },
    update: {
        field: 'saveComment',
        args: { ...SAVE, uid: 'String' },
        selection: '{ commentsData }',
        variables: saveVariables,
        result: (value) => {
            const comment = commentsData(value);
            return { comment, moderationState: moderationState(comment) };
        },
    },
    delete: {
        field: 'deleteComment',
        args: OPERATION,
        selection: '',
        result: (_value, body) => ({ uid: body.uid, conversation: null }),
    },
    vote: {
        field: 'voteComment',
        args: { ...OPERATION, vote: 'String' },
        selection: '{ comment { commentsData } }',
        result: (value, body) => ({ comment: patch(value, body, true) }),
    },
    report: {
        field: 'flagComment',
        args: { ...OPERATION, reason: 'String', details: 'String', withdraw: 'Boolean' },
        selection: '{ comment { commentsData } }',
        result: (value, body) => ({ comment: patch(value, body, false) }),
    },
    follow: {
        field: 'subscribeComment',
        args: { operationId: 'String', ownerId: 'ID!', siteId: 'ID', following: 'Boolean', email: 'String' },
        selection: '',
        // The mutation answers with the outcome alone, so the follow state is read from it: a guest
        // still confirming their address needs to give it, and anyone else is done.
        result: (value) => {
            if (value !== 'followed' && value !== 'unfollowed' && value !== 'confirming') {
                throw unexpected('no follow outcome');
            }
            return { viewer: { follow: { available: true, following: value === 'followed', needsEmail: value === 'confirming' } }, outcome: value };
        },
    },
    moderate: {
        field: 'moderateComment',
        args: { ...OPERATION, action: 'String!', reason: 'String', note: 'String' },
        selection: '{ commentsData }',
        result: (value) => ({ comment: commentsData(value) }),
    },
};
/** Writes `query CommentsThread($ownerId: ID, …) { commentsThread(ownerId: $ownerId, …) { … } }`. */
function operation(type, name, field, args, selection, extra = '') {
    const names = Object.keys(args);
    const variables = names.map((arg) => `$${arg}: ${args[arg]}`).join(', ');
    const values = names.map((arg) => `${arg}: $${arg}`).join(', ');
    return `${type} ${name}(${variables}) { ${field}(${values}${extra}) ${selection} }`;
}
const capitalise = (value) => value[0].toUpperCase() + value.slice(1);
/**
 * Reads through the `commentsThread`, `commentsReplies`, `commentsComment` and `commentsViewer`
 * queries and writes through the existing mutations, answering in the REST shapes so a thread
 * store sees the same data from either transport. Everything else (`session`, `protection`,
 * `structured-data`, `edit`, `mentions` and `upload`) goes through REST. It's a separate entry,
 * `@verbb/comments-core/graphql`, so sites that only use REST don't bundle it.
 */
export function graphqlTransport(options) {
    return {
        name: 'graphql',
        protectionChannel: 'headless',
        handles: (request) => (request.kind === 'read' ? request.action in READS : request.action in WRITES && !(request.body instanceof FormData)),
        async send(request) {
            if (request.kind === 'read') {
                const args = READS[request.action];
                const field = `comments${capitalise(request.action)}`;
                // Only the query's own arguments; `v`, which only gives a CDN a new URL, isn't one.
                const variables = Object.fromEntries(Object.entries(request.query ?? {}).filter(([name, value]) => name in args && value !== undefined && value !== ''));
                const response = await post(options, request, operation('query', capitalise(field), field, args, '{ apiVersion data }'), variables);
                return unwrap(response, field, (value) => {
                    const wrapper = (value ?? {});
                    return { apiVersion: wrapper.apiVersion, status: 'ok', ...wrapper.data };
                });
            }
            const write = WRITES[request.action];
            const body = (request.body ?? {});
            const fields = body.fields;
            const query = operation('mutation', `Comments${capitalise(request.action)}`, write.field, write.args, write.selection, fieldArguments(fields, Object.keys(write.args)));
            const variables = Object.fromEntries(Object.keys(write.args).map((arg) => [arg, body[arg] ?? null]));
            const response = await post(options, request, query, { ...variables, ...write.variables?.(body) });
            return unwrap(response, write.field, (value) => ({
                apiVersion: COMMENTS_API_VERSION,
                status: 'ok',
                replayed: false,
                announcement: null,
                ...write.result(value, body),
            }));
        },
    };
}
async function post(options, request, query, variables) {
    const response = await resolveFetch(options.fetch)(options.endpoint, {
        method: 'POST',
        headers: { ...request.headers, 'Content-Type': 'application/json' },
        body: toJson({ query, variables }),
        credentials: request.credentials,
        redirect: request.redirect,
        signal: request.signal,
    });
    return { status: response.status, headers: response.headers, payload: await readJson(response) };
}
/** GraphQL errors become the REST error shape, reading `code` and the rest from `extensions`. */
function unwrap(response, field, map) {
    const payload = (response.payload ?? {});
    if (payload.errors?.length) {
        return {
            ...response,
            payload: {
                apiVersion: COMMENTS_API_VERSION,
                status: 'error',
                // Comments' own errors carry a code and a message for the visitor. Anything else,
                // such as a schema error, isn't for them to read.
                errors: payload.errors.map((error) => normalizeErrorData(typeof error.extensions?.code === 'string'
                    ? { ...error.extensions, message: error.message }
                    : { code: 'server_error' })),
            },
            detail: payload.errors.map((error) => error.message).join(' '),
        };
    }
    if (!payload.data || !(field in payload.data)) {
        throw unexpected(`no ${field}`);
    }
    return { ...response, payload: map(payload.data[field]) };
}
function saveVariables(body) {
    const content = body.content;
    return {
        operationId: body.operationId,
        comment: content?.format === 'plain' ? content.text : null,
        contentFormat: content ? content.format : null,
        contentDocument: content?.format === 'rich' ? JSON.stringify(content.document) : null,
        protection: body.protection ? graphqlEvidence(body.protection) : null,
    };
}
/** `CommentsProtectionEvidence` takes challenge tokens by camel-cased provider handle. */
function graphqlEvidence(evidence) {
    const challenges = Object.fromEntries(Object.entries(evidence.challenges).map(([handle, token]) => [handle.replace(/-([a-z\d])/g, (_match, letter) => letter.toUpperCase()), token]));
    return { version: evidence.version, preparation: evidence.preparation, challenges };
}
const moderationState = (comment) => (comment.state === 'approved' ? 'approved' : 'pending');
function unexpected(detail) {
    return CommentsRequestError.from('invalid_response', GENERIC_MESSAGE, { detail: `GraphQL: ${detail}` });
}
function commentsData(value) {
    const data = (value ?? {}).commentsData;
    if (!data || typeof data !== 'object' || typeof data.uid !== 'string') {
        throw unexpected('no commentsData');
    }
    return data;
}
/** A vote or report's comment. `flagComment` can answer null after a withdrawal, leaving only the UID. */
function patch(value, body, withInteraction) {
    const comment = (value ?? {}).comment;
    const data = comment ? commentsData(comment) : null;
    const result = { uid: body.uid };
    if (data?.viewer)
        result.viewer = data.viewer;
    if (withInteraction && data?.interaction)
        result.interaction = data.interaction;
    return result;
}
