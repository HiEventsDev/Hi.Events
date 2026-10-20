import axios from "axios";
import {isSsr} from "../utilites/helpers";
import {getConfig} from "../utilites/config";
import {getCheckoutSessionIdentifier} from "../utilites/checkoutSession";
import {applySsrRequestHeaders} from "./ssrRequestHeaders";
import {
    BOX_OFFICE_SESSION_HEADER,
    getBoxOfficeSessionToken,
    notifyBoxOfficeSessionExpired,
    notifyBoxOfficeUnavailable,
} from "../ee/box-office/utilites/boxOfficeSession";

export const publicApi = axios.create({
    withCredentials: true,
});

publicApi.interceptors.request.use(applySsrRequestHeaders);

publicApi.interceptors.request.use((config) => {
    const baseUrl = isSsr()
        ? getConfig('VITE_API_URL_SERVER')
        : getConfig('VITE_API_URL_CLIENT');

    config.baseURL = `${baseUrl}/public`;

    const boxOfficeShortId = config.url?.match(/\/box-offices\/([^/?#]+)\//)?.[1];
    if (boxOfficeShortId && !config.url?.endsWith('/sessions')) {
        const token = getBoxOfficeSessionToken(boxOfficeShortId);
        if (token) {
            config.headers.set(BOX_OFFICE_SESSION_HEADER, token);
        }
    }

    const orderShortId = config.url?.match(/\/order\/([^/?#]+)/)?.[1];
    if (orderShortId && !config.url?.includes('session_identifier=')) {
        const token = getCheckoutSessionIdentifier(orderShortId);
        if (token) {
            config.params = {...config.params, session_identifier: token};
        }
    }

    return config;
}, (error) => {
    return Promise.reject(error);
});

publicApi.interceptors.response.use((response) => response, (error) => {
    if (error?.response?.status === 401 && error.response.data?.error_code === 'BOX_OFFICE_SESSION_EXPIRED') {
        notifyBoxOfficeSessionExpired();
    }
    if (error?.response?.status === 409 && error.response.data?.error_code === 'BOX_OFFICE_UNAVAILABLE') {
        notifyBoxOfficeUnavailable();
    }
    return Promise.reject(error);
});

axios.defaults.withCredentials = true;
