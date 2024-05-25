import {
    AcceptInvitationRequest,
    GenericDataResponse,
    LoginData,
    LoginResponse, RegisterAccountRequest,
    ResetPasswordRequest,
    User
} from "../types.ts";
import {api} from './client.ts';

export const authClient = {
    refreshAccessTokenFn: async () => {
        const response = await api.get<LoginResponse>('auth/refresh');
        return response.data;
    },

    register: async (registerData: RegisterAccountRequest) => {
        const response = await api.post<GenericDataResponse<User>>('auth/register', registerData);
        return response.data;
    },

    login: async (user: LoginData) => {
        const response = await api.post<LoginResponse>('auth/login', user);
        return response.data;
    },

    logout: async () => {
        const response = await api.post('auth/logout');
        return response.data;
    },

    forgotPassword: async (email: { email: string }) => {
        const response = await api.post('auth/forgot-password', email);
        return response.data;
    },

    verifyPasswordResetToken: async (token: string) => {
        const response = await api.get(`auth/reset-password/${token}`);
        return response.data;
    },

    resetPassword: async (token: string, resetData: ResetPasswordRequest) => {
        const response = await api.post(`auth/reset-password/${token}`, resetData);
        return response.data;
    },

    getInvitation: async (token: string) => {
        const response = await api.get<GenericDataResponse<User>>(`auth/invitation/${token}`);
        return response.data;
    },

    acceptInvitation: async (token: string, acceptData: AcceptInvitationRequest) => {
        const response = await api.post(`auth/invitation/${token}`, acceptData);
        return response.data;
    }
}