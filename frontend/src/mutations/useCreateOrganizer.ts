import {useMutation, useQueryClient} from "@tanstack/react-query";
import {Organizer} from "../types.ts";
import {GET_ORGANIZERS_QUERY_KEY} from "../queries/useGetOrganizers.ts";
import {organizerClient} from "../api/organizer.client.ts";

export const useCreateOrganizer = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerData}: {
            organizerData: Partial<Organizer>
        }) => organizerClient.create(organizerData),

        onSuccess: () => queryClient.invalidateQueries({queryKey: [GET_ORGANIZERS_QUERY_KEY]})
    });
}