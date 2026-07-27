import axios from "axios";
import {isSsr} from "../utilites/helpers";
import {getConfig} from "../utilites/config";
import {getCheckoutSessionIdentifier} from "../utilites/checkoutSession";

export const publicApi = axios.create({
    withCredentials: true,
});

publicApi.interceptors.request.use((config) => {
    const baseUrl = isSsr()
        ? getConfig('VITE_API_URL_SERVER')
        : getConfig('VITE_API_URL_CLIENT');

    config.baseURL = `${baseUrl}/public`;

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

axios.defaults.withCredentials = true;
