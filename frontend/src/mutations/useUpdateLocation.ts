import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {locationClient, UpsertLocationPayload} from "../api/location.client.ts";
import {GET_ORGANIZER_LOCATIONS_QUERY_KEY} from "../queries/useGetOrganizerLocations.ts";

export const useUpdateLocation = () => {
    const queryClient = useQueryClient();
    return useMutation({
        mutationFn: ({organizerId, locationId, payload}: {
            organizerId: IdParam;
            locationId: IdParam;
            payload: UpsertLocationPayload;
        }) => locationClient.update(organizerId, locationId, payload),
        onSuccess: (_, vars) =>
            queryClient.invalidateQueries({queryKey: [GET_ORGANIZER_LOCATIONS_QUERY_KEY, vars.organizerId]}),
    });
};
