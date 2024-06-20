import {api} from "./client";
import {GenericDataResponse, IdParam, InviteUserRequest, User} from "../types";

export interface UserMeRequest {
    first_name: string;
    last_name: string;
    email: string;
    timezone: string;
    password: string;
    password_confirmation: string;
    password_current: string;
    locale: string;
}

export interface UpdateUserRequest {
    first_name: string;
    last_name: string;
    role: string;
    status: string;
}

export const userClient = {
    confirmEmailAddress: async (userId: IdParam, token: string) => {
        const response = await api.post<GenericDataResponse<User>>(`users/${userId}/confirm-email/${token}`);
        return response.data;
    },
    confirmEmailChange: async (userId: IdParam, token: string) => {
        const response = await api.post<GenericDataResponse<User>>(`users/${userId}/email-change/${token}`);
        return response.data;
    },
    cancelEmailChange: async (userId: IdParam) => {
        const response = await api.delete<GenericDataResponse<User>>(`users/${userId}/email-change`);
        return response.data;
    },
    updateMe: async (updateParams: Partial<UserMeRequest>) => {
        const response = await api.put<GenericDataResponse<User>>(`users/me`, updateParams);
        return response.data;
    },
    updateUser: async (userId: IdParam, updateParams: UpdateUserRequest) => {
        const response = await api.put<GenericDataResponse<User>>(`users/${userId}`, updateParams);
        return response.data;
    },
    all: async () => {
        const response = await api.get<GenericDataResponse<User[]>>('users');
        return response.data;
    },
    invite: async (userData: InviteUserRequest) => {
        const response = await api.post<GenericDataResponse<User>>(`users`, userData);
        return response.data;
    },
    me: async () => {
        const response = await api.get<GenericDataResponse<User>>('users/me');
        return response.data;
    },
    resendInvitation: async (userId: IdParam) => {
        const response = await api.post(`users/${userId}/invitation`);
        return response.data;
    },
    findByID: async (userId: IdParam) => {
        const response = await api.get<GenericDataResponse<User>>(`users/${userId}`);
        return response.data;
    },
    deleteInvitation: async (userId: IdParam) => {
        const response = await api.delete(`users/${userId}/invitation`);
        return response.data;
    },
    resendConfirmation: async (userId: IdParam) => {
        const response = await api.post(`users/${userId}/resend-email-confirmation`);
        return response.data;
    },
};