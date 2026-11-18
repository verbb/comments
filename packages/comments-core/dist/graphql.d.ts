import { type CommentsTransport } from './transport.js';
export type GraphqlTransportOptions = {
    /** Craft's GraphQL endpoint, such as `https://example.com/api`. */
    endpoint: string;
    fetch?: typeof fetch;
};
/**
 * Reads through the `commentsThread`, `commentsReplies`, `commentsComment` and `commentsViewer`
 * queries and writes through the existing mutations, answering in the REST shapes so a thread
 * store sees the same data from either transport. Everything else (`session`, `protection`,
 * `structured-data`, `edit`, `mentions` and `upload`) goes through REST. It's a separate entry,
 * `@verbb/comments-core/graphql`, so sites that only use REST don't bundle it.
 */
export declare function graphqlTransport(options: GraphqlTransportOptions): CommentsTransport;
