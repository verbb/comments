/**
 * The data API's JSON, as `comments/api/*` and the GraphQL `comments*` queries send it.
 *
 * `scripts/generate-schema.mjs` turns every type exported here into `schema/data.json`, which
 * the server's tests validate each response against. Keep this file to plain JSON shapes (no
 * generics or functions), and describe a field here rather than in the schema, which is generated.
 */
export {};
