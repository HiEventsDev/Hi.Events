import {useMutation} from "@tanstack/react-query";
import {userClient} from "../api/user.client.ts";

export const useDeleteAccount = () => {
    return useMutation({
        mutationFn: (payload: { confirmation: string; password: string }) => userClient.deleteMyAccount(payload),
    });
};
