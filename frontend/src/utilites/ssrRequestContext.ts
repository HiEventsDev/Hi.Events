import {QueryClient} from "@tanstack/react-query";

export interface SsrRequestContext {
    queryClient: QueryClient;
    authToken?: string;
    backendHeaders: Record<string, string>;
    licenceSimulation?: string;
}

let contextProvider: (() => SsrRequestContext | undefined) | null = null;

export const setSsrRequestContextProvider = (provider: () => SsrRequestContext | undefined) => {
    contextProvider = provider;
};

export const getSsrRequestContext = (): SsrRequestContext | undefined => contextProvider?.();
