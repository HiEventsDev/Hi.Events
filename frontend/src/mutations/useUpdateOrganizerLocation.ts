import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {organizerClient} from "../api/organizer.client.ts";
import {GET_ORGANIZER_QUERY_KEY} from "../queries/useGetOrganizer.ts";

export const useUpdateOrganizerLocation = () => {
    const queryClient = useQueryClient();
    return useMutation({
        mutationFn: ({organizerId, locationId}: {organizerId: IdParam; locationId: IdParam | null}) =>
            organizerClient.updateLocation(organizerId, locationId),
        onSuccess: (_, vars) =>
            queryClient.invalidateQueries({queryKey: [GET_ORGANIZER_QUERY_KEY, vars.organizerId]}),
    });
};
