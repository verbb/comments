/**
 * A new `operationId` for one write: a version 4 UUID. Falls back to `getRandomValues()`, because
 * `randomUUID()` is missing outside secure contexts, such as a site on plain `http` other than
 * localhost.
 */
export declare function createOperationId(): string;
