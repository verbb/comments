import { getCurrentInstance, isRef, onMounted, onScopeDispose, toValue, watch } from 'vue';
export function useCommentsEvent(name, handler, options = {}) {
    let stop = null;
    const start = () => {
        if ('thread' in options) {
            stop = watch(() => {
                const thread = toValue(options.thread);
                if (!thread)
                    return null;
                if ('getState' in thread)
                    return thread;
                return isRef(thread.store) ? thread.store.value : thread.store;
            }, (store, _previous, onCleanup) => {
                const off = store?.on(name, (detail) => handler(detail));
                onCleanup(() => off?.());
            }, { immediate: true });
            return;
        }
        stop = watch(() => toValue(options.target) ?? document, (target, _previous, onCleanup) => {
            const listener = (event) => {
                if (options.instanceId && event.detail?.instanceId !== options.instanceId)
                    return;
                handler(event);
            };
            target.addEventListener(name, listener);
            onCleanup(() => target.removeEventListener(name, listener));
        }, { immediate: true });
    };
    if (getCurrentInstance())
        onMounted(start);
    else if (typeof document !== 'undefined')
        start();
    onScopeDispose(() => stop?.());
}
