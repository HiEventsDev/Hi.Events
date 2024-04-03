import axios from "axios";

const BASE_URL = import.meta.env.VITE_API_URL + '/public';

export const publicApi = axios.create({
    baseURL: BASE_URL,
});

//For the public client we set the token as we want to know if the user is logged in
const setAuthToken = (token: string) => {
    if (token) {
        // eslint-disable-next-line lingui/no-unlocalized-strings
        publicApi.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    }
};

const existingToken = window.localStorage?.getItem('token');

if (existingToken) {
    setAuthToken(existingToken);
}

axios.defaults.withCredentials = true
