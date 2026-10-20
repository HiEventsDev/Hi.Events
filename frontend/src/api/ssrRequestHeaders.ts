import {InternalAxiosRequestConfig} from "axios";
import {isSsr} from "../utilites/helpers.ts";
import {getSsrRequestContext} from "../utilites/ssrRequestContext.ts";

export const applySsrRequestHeaders = (config: InternalAxiosRequestConfig) => {
    if (!isSsr()) {
        return config;
    }

    const context = getSsrRequestContext();

    if (context?.authToken) {
        config.headers.set('Authorization', `Bearer ${context.authToken}`);
    } else {
        config.headers.delete('Authorization');
    }

    Object.entries(context?.backendHeaders ?? {}).forEach(([name, value]) => {
        config.headers.set(name, value);
    });

    return config;
};
