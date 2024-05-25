import {api} from "../api/client.ts";
import {publicApi} from "../api/public-client.ts";

export const setAuthToken = (token: string) => {
    api.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    publicApi.defaults.headers.common['Authorization'] = `Bearer ${token}`;
};
