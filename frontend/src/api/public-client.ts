import axios from "axios";
import {isSsr} from "../utilites/helpers";
import {getConfig} from "../utilites/config";
import {startOidcLogin} from "../utilites/oidcLogin.ts";

export const publicApi = axios.create({
    withCredentials: true,
});

publicApi.interceptors.request.use((config) => {
    const baseUrl = isSsr()
        ? getConfig('VITE_API_URL_SERVER')
        : getConfig('VITE_API_URL_CLIENT');

    config.baseURL = `${baseUrl}/public`;
    return config;
}, (error) => {
    return Promise.reject(error);
});

publicApi.interceptors.response.use(
    (response) => response,
    (error) => {
        const status = error?.response?.status;
        const url = error?.config?.url ?? '';

        if ((status === 401 || status === 403)) {
            startOidcLogin(typeof window !== 'undefined' ? window.location.href : undefined);
        }

        return Promise.reject(error);
    }
);

axios.defaults.withCredentials = true;
