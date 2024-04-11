import axios from "axios";
import {setAuthToken} from "../utilites/apiClient.ts";
import {isSsr} from "../utilites/helpers.ts";

const BASE_URL =
    (isSsr()
        ? import.meta.env.VITE_API_URL_SERVER
        : import.meta.env.VITE_API_URL_CLIENT) + '/public';

export const publicApi = axios.create({
    baseURL: BASE_URL,
});

const existingToken = typeof window !== "undefined" ? window?.localStorage?.getItem('token') : undefined;

if (existingToken) {
    setAuthToken(existingToken);
}

axios.defaults.withCredentials = true
