import {useMutation} from "@tanstack/react-query";
import {AcceptInvitationRequest} from "../types.ts";
import {authClient} from "../api/auth.client.ts";

export const useAcceptInvitation = () => {
    return useMutation({
        mutationFn: ({token, userData}: {
            token: string,
            userData: AcceptInvitationRequest,
        }) => authClient.acceptInvitation(token, userData)
    });
}