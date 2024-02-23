import {useQuery} from "@tanstack/react-query";
import {authClient} from "../api/auth.client.ts";

export const VERIFY_PASSWORD_TOKEN_QUERY_KEY = 'verifyPasswordResetToken';

export const useVerifyPasswordResetToken = (token: string) => {
    return useQuery(
        [VERIFY_PASSWORD_TOKEN_QUERY_KEY, token],
        async () => {
            return await authClient.verifyPasswordResetToken(token);
        },
        {
            retry: false,
        }
    );
}