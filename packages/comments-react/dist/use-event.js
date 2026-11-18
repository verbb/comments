import { useEffect, useRef } from 'react';
export function useCommentsEvent(name, handler, options = {}) {
    const latest = useRef(handler);
    const attached = useRef(null);
    useEffect(() => {
        latest.current = handler;
    });
    // After every commit, so a ref's element that changes, or a store that arrives, is followed;
    // the listener is only replaced when what it listens to changes.
    useEffect(() => {
        const thread = 'thread' in options ? options.thread : undefined;
        const store = !thread ? null : 'getState' in thread ? thread : thread.store;
        const target = 'thread' in options ? null : options.target;
        const element = 'thread' in options ? null : (target && 'current' in target ? target.current : target ?? (typeof document === 'undefined' ? null : document));
        const source = 'thread' in options ? store : element;
        const instanceId = 'thread' in options ? undefined : options.instanceId;
        if (attached.current && attached.current.source === source && attached.current.name === name && attached.current.instanceId === instanceId)
            return;
        attached.current?.stop();
        attached.current = null;
        if (!source)
            return;
        let stop;
        if (store) {
            stop = store.on(name, (detail) => latest.current(detail));
        }
        else {
            const listener = (event) => {
                if (instanceId && event.detail?.instanceId !== instanceId)
                    return;
                latest.current(event);
            };
            element.addEventListener(name, listener);
            stop = () => element.removeEventListener(name, listener);
        }
        attached.current = { source, name, instanceId, stop };
    });
    useEffect(() => () => {
        attached.current?.stop();
        attached.current = null;
    }, []);
}
