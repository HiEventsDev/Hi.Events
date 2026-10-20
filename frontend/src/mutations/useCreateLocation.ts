import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {locationClient, UpsertLocationPayload} from "../api/location.client.ts";
import {GET_ORGANIZER_LOCATIONS_QUERY_KEY} from "../queries/useGetOrganizerLocations.ts";

export const useCreateLocation = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId, payload}: {organizerId: IdParam; payload: UpsertLocationPayload}) =>
            locationClient.create(organizerId, payload),
        onSuccess: (_, variables) => {
            return queryClient.invalidateQueries({queryKey: [GET_ORGANIZER_LOCATIONS_QUERY_KEY, variables.organizerId]});
        },
    });
};
