import {useEffect, useSyncExternalStore} from "react";

const STALE_CHUNK_RELOAD_KEY = 'hi_lazy_module_reloaded_at';
const STALE_CHUNK_RELOAD_WINDOW_MS = 60_000;

const reloadOnceForStaleChunks = () => {
    try {
        const lastReload = Number(window.sessionStorage.getItem(STALE_CHUNK_RELOAD_KEY));
        if (lastReload && Date.now() - lastReload < STALE_CHUNK_RELOAD_WINDOW_MS) {
            return;
        }
        window.sessionStorage.setItem(STALE_CHUNK_RELOAD_KEY, String(Date.now()));
    } catch {
        return;
    }
    window.location.reload();
};

export const createLazyModule = <T, >(importModule: () => Promise<T>) => {
    let loaded: T | null = null;
    let pending: Promise<T> | null = null;
    const listeners = new Set<() => void>();

    const load = () => {
        pending ??= importModule().then((module) => {
            loaded = module;
            listeners.forEach((listener) => listener());
            return module;
        }, (error) => {
            pending = null;
            throw error;
        });
        return pending;
    };

    const subscribe = (listener: () => void) => {
        listeners.add(listener);
        return () => {
            listeners.delete(listener);
        };
    };

    const useModule = (): T | null => {
        const module = useSyncExternalStore(subscribe, () => loaded, () => null);
        useEffect(() => {
            if (!module) {
                load().catch(reloadOnceForStaleChunks);
            }
        }, [module]);
        return module;
    };

    return {load, useModule};
};
