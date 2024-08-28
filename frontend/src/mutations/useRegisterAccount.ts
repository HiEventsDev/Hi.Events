import {useMutation} from "@tanstack/react-query";
import {RegisterAccountRequest} from "../types.ts";
import {authClient} from "../api/auth.client.ts";

export const useRegisterAccount = () => {
    return useMutation({
        mutationFn: ({registerData}: {
            registerData: RegisterAccountRequest,
        }) => authClient.register(registerData)
    });
}
