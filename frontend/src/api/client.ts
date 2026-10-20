import axios from "axios";
import {isSsr} from "../utilites/helpers.ts";
import {getConfig} from "../utilites/config.ts";
import {applySsrRequestHeaders} from "./ssrRequestHeaders.ts";
import {queueSilentReauthAfterExpiry, setAuthNotice} from "../utilites/ssoFlow.ts";
import {LOGIN_PATH, resolveAuthErrorAction, TWO_FACTOR_SETUP_PATH} from "./authErrorRedirect.ts";

export {TWO_FACTOR_SETUP_PATH};

const BASE_URL = isSsr()
    ? getConfig('VITE_API_URL_SERVER')
    : getConfig('VITE_API_URL_CLIENT');
export const SECURITY_SETTINGS_PATH = "/settings/security";
const PREVIOUS_URL_KEY = 'previous_url';

export const api = axios.create({
    baseURL: BASE_URL,
    headers: {
        'Content-Type': 'application/json'
    },
    withCredentials: true,
});

api.interceptors.request.use(applySsrRequestHeaders);

api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (!error.response) {
            return Promise.reject(error);
        }
        const {status, data} = error.response;
        const action = resolveAuthErrorAction(status, data?.error_code, window?.location.pathname);

        if (action.type === 'redirect') {
            window?.location?.replace(action.to);
        }

        if (action.type === 'login') {
            if (data?.error_code === 'SSO_REQUIRED') {
                setAuthNotice('SSO_REQUIRED');
            } else if (status === 401) {
                queueSilentReauthAfterExpiry();
            }
            window?.localStorage?.setItem(PREVIOUS_URL_KEY, window?.location.href);
            const searchParams = window?.location?.search || '';
            window?.location?.replace(LOGIN_PATH + searchParams);
        }

        return Promise.reject(error);
    }
);

axios.defaults.withCredentials = true;

export const setPreviousUrl = (url: string) => {
    window?.localStorage?.setItem(PREVIOUS_URL_KEY, url);
};

export const redirectToPreviousUrl = (replaceHistory = false) => {
    const previousUrl = window?.localStorage?.getItem(PREVIOUS_URL_KEY) || '/manage/events';
    window?.localStorage?.removeItem(PREVIOUS_URL_KEY);
    if (typeof window === "undefined") {
        return;
    }

    if (replaceHistory) {
        window.location.replace(previousUrl);
    } else {
        window.location.href = previousUrl;
    }
};
