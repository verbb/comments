/**
 * A string that's the same for equal values whatever their key order, so options written inline on
 * every render don't count as a change. Functions count as equal to each other; callers that care
 * about a function's identity compare it themselves.
 */
export function stableKey(value) {
    return serialize(value, new WeakSet());
}
function serialize(value, seen) {
    if (value === undefined)
        return 'undefined';
    if (value === null || typeof value === 'number' || typeof value === 'boolean')
        return String(value);
    if (typeof value === 'string')
        return JSON.stringify(value);
    if (typeof value === 'function')
        return '[function]';
    if (typeof value !== 'object')
        return JSON.stringify(String(value));
    if (seen.has(value))
        return '[circular]';
    seen.add(value);
    const out = Array.isArray(value)
        ? `[${value.map((item) => serialize(item, seen)).join(',')}]`
        : `{${Object.keys(value).sort().map((key) => `${JSON.stringify(key)}:${serialize(value[key], seen)}`).join(',')}}`;
    seen.delete(value);
    return out;
}
