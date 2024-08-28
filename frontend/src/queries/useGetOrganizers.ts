import {useQuery} from "@tanstack/react-query";
import {organizerClient} from "../api/organizer.client.ts";

export const GET_ORGANIZERS_QUERY_KEY = 'getOrganizers';

export const useGetOrganizers = () => {
    return useQuery({
        queryKey: [GET_ORGANIZERS_QUERY_KEY],

        queryFn: async () => {
            return await organizerClient.all();
        }
    });
};