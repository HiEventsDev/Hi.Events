import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {locationClient} from "../api/location.client.ts";
import {GET_ORGANIZER_LOCATIONS_QUERY_KEY} from "../queries/useGetOrganizerLocations.ts";

export const useDeleteLocation = () => {
    const queryClient = useQueryClient();
    return useMutation({
        mutationFn: ({organizerId, locationId}: {organizerId: IdParam; locationId: IdParam}) =>
            locationClient.delete(organizerId, locationId),
        onSuccess: (_, vars) =>
            queryClient.invalidateQueries({queryKey: [GET_ORGANIZER_LOCATIONS_QUERY_KEY, vars.organizerId]}),
    });
};
