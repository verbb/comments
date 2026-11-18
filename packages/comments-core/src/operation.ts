/**
 * A new `operationId` for one write: a version 4 UUID. Falls back to `getRandomValues()`, because
 * `randomUUID()` is missing outside secure contexts, such as a site on plain `http` other than
 * localhost.
 */
export function createOperationId(): string {
    const crypto = globalThis.crypto;

    if (typeof crypto?.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    const bytes = new Uint8Array(16);
    crypto.getRandomValues(bytes);
    bytes[6] = (bytes[6]! & 0x0f) | 0x40;
    bytes[8] = (bytes[8]! & 0x3f) | 0x80;
    const hex = Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');

    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}
