import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {organizerClient} from "../api/organizer.client.ts";

export const GET_ORGANIZER_DELETION_STATUS_QUERY_KEY = 'getOrganizerDeletionStatus';

export const useGetOrganizerDeletionStatus = (organizerId: IdParam) => {
    return useQuery({
        queryKey: [GET_ORGANIZER_DELETION_STATUS_QUERY_KEY, organizerId],

        staleTime: 0,
        queryFn: async () => {
            const {data} = await organizerClient.getDeletionStatus(organizerId);
            return data;
        }
    });
};
