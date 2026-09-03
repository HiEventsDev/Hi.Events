import {useMutation, useQueryClient} from "@tanstack/react-query";
import {adminClient} from "../api/admin.client";
import {IdParam} from "../types";
import {GET_ADMIN_SPAM_EVENTS_QUERY_KEY} from "../queries/useGetAdminSpamEvents";

export const useConfirmSpamEvent = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (eventId: IdParam) => adminClient.confirmSpamEvent(eventId),
        onSuccess: () => {
            queryClient.invalidateQueries({queryKey: GET_ADMIN_SPAM_EVENTS_QUERY_KEY});
        },
    });
};
