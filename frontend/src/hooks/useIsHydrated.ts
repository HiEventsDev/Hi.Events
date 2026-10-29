import {useSyncExternalStore} from "react";

const subscribe = () => () => undefined;

export const useIsHydrated = (): boolean => useSyncExternalStore(subscribe, () => true, () => false);
