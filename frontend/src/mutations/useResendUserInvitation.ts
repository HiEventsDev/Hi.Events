import {useMutation} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {userClient} from "../api/user.client.ts";

export const useResendUserInvitation = () => {
    return useMutation({
        mutationFn: ({userId}: {
            userId: IdParam,
        }) => userClient.resendInvitation(userId)
    });
}