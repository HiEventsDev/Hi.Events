import axios from "axios";
import {setAuthToken} from "../utilites/apiClient.ts";
import {isSsr} from "../utilites/helpers.ts";
import {getConfig} from "../utilites/config.ts";

const BASE_URL = isSsr()
    ? getConfig('VITE_API_URL_SERVER')
    : getConfig('VITE_API_URL_CLIENT');
const LOGIN_PATH = "/auth/login";
const PREVIOUS_URL_KEY = 'previous_url'; // Key for storing the previous URL

const ALLOWED_UNAUTHENTICATED_PATHS = [
    'auth/login',
    'accept-invitation',
    'register',
    'forgot-password',
    'auth',
    'account/payment'
];

export const api = axios.create({
    baseURL: BASE_URL,
    headers: {
        'Content-Type': 'application/json'
    },
    withCredentials: true,
});


const existingToken = typeof window !== "undefined" ? window.localStorage.getItem('token') : undefined;
if (existingToken) {
    setAuthToken(existingToken);
}

api.interceptors.response.use(
    (response) => {
        // Securely update the token on each response
        // eslint-disable-next-line lingui/no-unlocalized-strings
        const token = response?.data?.token || response?.headers["x-auth-token"];

        if (token) {
            window?.localStorage?.setItem('token', token);
            setAuthToken(token);
        }
        return response;
    },
    (error) => {
        const { status } = error.response;
        if ((status === 401 || status === 403) && !ALLOWED_UNAUTHENTICATED_PATHS.some(path => window?.location.pathname.includes(path))) {
            // Store the current URL before redirecting to the login page
            window?.localStorage?.setItem(PREVIOUS_URL_KEY, window?.location.href);
            window?.location?.replace(LOGIN_PATH);
        }
        return Promise.reject(error);
    }
);

axios.defaults.withCredentials = true

export const redirectToPreviousUrl = () => {
    const previousUrl = window?.localStorage?.getItem(PREVIOUS_URL_KEY) || '/manage/events';
    window?.localStorage?.removeItem(PREVIOUS_URL_KEY); // Clean up after redirecting
    if (typeof window !== "undefined") {
        window.location.href = previousUrl;
    }
};
