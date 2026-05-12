import {api} from "./client.ts";
import {Account, GenericDataResponse, User} from "../types.ts";

interface CreateAccountRequest {
    first_name: string;
    last_name: string;
    email: string;
    password?: string;
}

export const accountClient = {
    create: async (account: CreateAccountRequest) => {
        const response = await api.post<GenericDataResponse<User>>('accounts', account);
        return response.data;
    },
    getAccount: async () => {
        const response = await api.get<GenericDataResponse<Account>>('accounts');
        return response.data;
    },
    updateAccount: async (account: Account) => {
        const response = await api.put<GenericDataResponse<Account>>('accounts', account);
        return response.data;
    },
}
