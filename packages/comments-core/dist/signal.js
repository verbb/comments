/**
 * A signal that aborts when the caller's does or after `timeoutMs`, with `cleanup()` to clear the
 * timer. Written out rather than using `AbortSignal.any()` and `AbortSignal.timeout()`, which
 * need Safari 17.4 and 16, below the supported Safari 15.4.
 */
export function requestSignal(signal, timeoutMs) {
    const controller = new AbortController();
    let timedOut = false;
    const abort = () => controller.abort(signal?.reason);
    const timer = timeoutMs > 0 ? setTimeout(() => {
        timedOut = true;
        controller.abort();
    }, timeoutMs) : undefined;
    if (signal?.aborted) {
        abort();
    }
    else {
        signal?.addEventListener('abort', abort, { once: true });
    }
    return {
        signal: controller.signal,
        timedOut: () => timedOut,
        cleanup() {
            clearTimeout(timer);
            signal?.removeEventListener('abort', abort);
        },
    };
}
/**
 * Waits for a promise, giving up when the signal aborts. The promise carries on, so work shared
 * by several callers, such as one `session` read, isn't cancelled by one of them leaving.
 */
export function abortable(promise, signal) {
    if (!signal)
        return promise;
    return new Promise((resolve, reject) => {
        const abort = () => reject(signal.reason ?? new DOMException('Aborted', 'AbortError'));
        if (signal.aborted) {
            abort();
            return;
        }
        signal.addEventListener('abort', abort, { once: true });
        promise.then(resolve, reject).finally(() => signal.removeEventListener('abort', abort));
    });
}
