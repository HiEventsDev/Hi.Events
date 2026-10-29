import {api} from "./client.ts";

export const apiReferenceClient = {
    get: async () => {
        const response = await api.get<Record<string, unknown>>('api-reference');
        return response.data;
    },
};
