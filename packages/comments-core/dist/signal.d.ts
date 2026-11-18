/**
 * A signal that aborts when the caller's does or after `timeoutMs`, with `cleanup()` to clear the
 * timer. Written out rather than using `AbortSignal.any()` and `AbortSignal.timeout()`, which
 * need Safari 17.4 and 16, below the supported Safari 15.4.
 */
export declare function requestSignal(signal: AbortSignal | undefined, timeoutMs: number): {
    signal: AbortSignal;
    timedOut: () => boolean;
    cleanup: () => void;
};
/**
 * Waits for a promise, giving up when the signal aborts. The promise carries on, so work shared
 * by several callers, such as one `session` read, isn't cancelled by one of them leaving.
 */
export declare function abortable<T>(promise: Promise<T>, signal: AbortSignal | undefined): Promise<T>;
