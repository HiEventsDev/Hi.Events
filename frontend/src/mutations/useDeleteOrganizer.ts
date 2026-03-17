import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {organizerClient} from "../api/organizer.client.ts";
import {GET_ORGANIZERS_QUERY_KEY} from "../queries/useGetOrganizers.ts";

export const useDeleteOrganizer = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId}: { organizerId: IdParam }) => organizerClient.delete(organizerId),

        onSuccess: () => {
            return queryClient.invalidateQueries({queryKey: [GET_ORGANIZERS_QUERY_KEY]});
        }
    });
};
