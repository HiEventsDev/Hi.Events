import {api} from "../api/client.ts";
import {publicApi} from "../api/public-client.ts";

export const setAuthToken = (token?: string | undefined | null) => {
    if (!token) {
        console.log('No token provided');
        delete api.defaults.headers.common['Authorization'];
        delete publicApi.defaults.headers.common['Authorization'];
        return;
    }

    console.log('Setting token:', token);
    api.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    publicApi.defaults.headers.common['Authorization'] = `Bearer ${token}`;
};
