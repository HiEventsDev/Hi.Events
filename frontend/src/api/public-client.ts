import axios from "axios";
import {isSsr} from "../utilites/helpers";
import {getConfig} from "../utilites/config";

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

axios.defaults.withCredentials = true;
