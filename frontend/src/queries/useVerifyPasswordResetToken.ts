import {useQuery} from "@tanstack/react-query";
import {authClient} from "../api/auth.client.ts";

export const VERIFY_PASSWORD_TOKEN_QUERY_KEY = 'verifyPasswordResetToken';

export const useVerifyPasswordResetToken = (token: string) => {
    return useQuery({
        queryKey: [VERIFY_PASSWORD_TOKEN_QUERY_KEY, token],

        queryFn: async () => {
            return await authClient.verifyPasswordResetToken(token);
        },

        retry: false
    });
}