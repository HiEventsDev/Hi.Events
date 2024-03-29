import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam, Organizer} from "../types.ts";
import {organizerClient} from "../api/organizer.client.ts";
import {GET_ORGANIZERS_QUERY_KEY} from "../queries/useGetOrganizers.ts";
import {GET_ORGANIZER_QUERY_KEY} from "../queries/useGetOrganizer.ts";

export const useUpdateOrganizer = () => {
    const queryClient = useQueryClient();

    return useMutation(
        ({organizerId, organizerData}: {
            organizerId: IdParam,
            organizerData: Partial<Organizer>,
        }) => organizerClient.update(organizerId, organizerData),
        {
            onSuccess: (_, variables) => {
                queryClient.invalidateQueries([GET_ORGANIZER_QUERY_KEY, variables.organizerId]);
                queryClient.invalidateQueries([GET_ORGANIZERS_QUERY_KEY]);
            },
        }
    )
}
