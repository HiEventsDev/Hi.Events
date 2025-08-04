import {useQuery} from "@tanstack/react-query";
import {IdParam, Organizer} from "../types.ts";
import {organizerClient} from "../api/organizer.client.ts";
import {AxiosError} from "axios";

export const GET_ORGANIZER_QUERY_KEY = 'getOrganizer';

export const getOrganizerQuery = (organizerId: IdParam) => ({
    queryKey: [GET_ORGANIZER_QUERY_KEY, organizerId],
    queryFn: async (): Promise<Organizer> => {
        const {data} = await organizerClient.findByID(organizerId);
        return data;
    },
    staleTime: 0,
    gcTime: 0
});

export const useGetOrganizer = (organizerId: IdParam) => {
    return useQuery<Organizer, AxiosError>(getOrganizerQuery(organizerId));
}

