import {api} from "./client.ts";
import {Account, AccountDeletionRequest, AccountDeletionStatus, GenericDataResponse, User} from "../types.ts";

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
    getDeletionStatus: async () => {
        const response = await api.get<GenericDataResponse<AccountDeletionStatus>>('accounts/deletion-request');
        return response.data;
    },
    requestDeletion: async (payload: { confirmation: string; reason?: string }) => {
        const response = await api.post<GenericDataResponse<AccountDeletionRequest>>('accounts/deletion-request', payload);
        return response.data;
    },
    cancelDeletion: async () => {
        const response = await api.delete<GenericDataResponse<AccountDeletionRequest>>('accounts/deletion-request');
        return response.data;
    },
}
