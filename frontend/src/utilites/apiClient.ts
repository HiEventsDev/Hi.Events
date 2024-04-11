import {publicApi} from "../api/public-client.ts";
import {api} from "../api/client.ts";

export const setAuthToken = (token: string) => {
    if (token) {
        // eslint-disable-next-line lingui/no-unlocalized-strings
        api.defaults.headers.common['Authorization'] = `Bearer ${token}`;
        publicApi.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    }
};
