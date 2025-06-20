import axios from "axios";
import {isSsr} from "../utilites/helpers.ts";
import {getConfig} from "../utilites/config.ts";

const BASE_URL = isSsr()
    ? getConfig('VITE_API_URL_SERVER')
    : getConfig('VITE_API_URL_CLIENT');
const LOGIN_PATH = "/auth/login";
const PREVIOUS_URL_KEY = 'previous_url';

// todo - This isn't scalable, we need to better way to manage this
const ALLOWED_UNAUTHENTICATED_PATHS = [
    'auth/login',
    'accept-invitation',
    'register',
    'forgot-password',
    'auth',
    'account/payment',
    'checkout',
    '/event/',
    'print',
    '/order/',
    'widget',
    '/product/',
    'check-in',
    '/events/'
];

export const api = axios.create({
    baseURL: BASE_URL,
    headers: {
        'Content-Type': 'application/json'
    },
    withCredentials: true,
});

api.interceptors.response.use(
    (response) => response,
    (error) => {
        const { status } = error.response;
        const currentPath = window?.location.pathname;
        const isAllowedUnauthenticatedPath = ALLOWED_UNAUTHENTICATED_PATHS.some(path => currentPath.includes(path));
        const isManageEventPath = currentPath.startsWith('/manage/event/');
        const isAuthError = status === 401 || status === 403;

        if (isAuthError && (!isAllowedUnauthenticatedPath || isManageEventPath)) {
            // Store the current URL before redirecting to the login page
            window?.localStorage?.setItem(PREVIOUS_URL_KEY, window?.location.href);
            window?.location?.replace(LOGIN_PATH);
        }

        return Promise.reject(error);
    }
);

axios.defaults.withCredentials = true;

export const redirectToPreviousUrl = () => {
    const previousUrl = window?.localStorage?.getItem(PREVIOUS_URL_KEY) || '/manage/events';
    window?.localStorage?.removeItem(PREVIOUS_URL_KEY);
    if (typeof window !== "undefined") {
        window.location.href = previousUrl;
    }
};
