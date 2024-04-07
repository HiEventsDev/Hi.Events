import axios from "axios";



const BASE_URL = import.meta.env.VITE_API_URL;
const LOGIN_PATH = "/auth/login";
const PREVIOUS_URL_KEY = 'previous_url'; // Key for storing the previous URL

const ALLOWED_UNAUTHENTICATED_PATHS = [
    'auth/login',
    'accept-invitation',
    'register',
    'forgot-password',
    'auth'
];

export const api = axios.create({
    baseURL: BASE_URL,
    headers: {
        'Content-Type': 'application/json'
    },
    // withCredentials: true,
});

export const setAuthToken = (token: string) => {
    if (token) {
        // eslint-disable-next-line lingui/no-unlocalized-strings
        api.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    }
};

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
            if (typeof document !== "undefined")
                document.cookie = `token=${token}; path=/; max-age=3600; secure; samesite=strict;`;
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
    if (typeof window !== "undefined")
        window.location.href = previousUrl;

};
