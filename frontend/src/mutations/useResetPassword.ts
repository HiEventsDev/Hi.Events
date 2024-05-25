import {ResetPasswordRequest} from "../types.ts";
import {authClient} from "../api/auth.client.ts";
import {useMutation} from "@tanstack/react-query";

export const useResetPassword = () => {
    return useMutation(
        ({token, resetData}: {
            token: string,
            resetData: ResetPasswordRequest,
        }) => authClient.resetPassword(token, resetData),
    )
}