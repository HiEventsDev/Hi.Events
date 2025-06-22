import {organizerClient} from "../api/organizer.client.ts";
import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {GET_ORGANIZER_QUERY_KEY} from "../queries/useGetOrganizer.ts";
import {GET_ORGANIZERS_QUERY_KEY} from "../queries/useGetOrganizers.ts";

export const useUpdateOrganizerStatus = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId, status}: {
            organizerId: IdParam,
            status: string,
        }) => organizerClient.updateStatus(organizerId, status),

        onSuccess: (_, variables) => {
            queryClient.invalidateQueries({
                queryKey: [GET_ORGANIZER_QUERY_KEY, variables.organizerId]
            });
            queryClient.invalidateQueries({
                queryKey: [GET_ORGANIZERS_QUERY_KEY]
            });
        }
    });
}