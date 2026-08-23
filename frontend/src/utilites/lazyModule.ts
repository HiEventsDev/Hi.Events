import {useEffect, useSyncExternalStore} from "react";

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
                load();
            }
        }, [module]);
        return module;
    };

    return {load, useModule};
};
