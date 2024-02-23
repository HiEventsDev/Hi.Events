import {useQuery} from "@tanstack/react-query";
import {organizerClient} from "../api/organizer.client.ts";

export const GET_ORGANIZERS_QUERY_KEY = 'getOrganizers';

export const useGetOrganizers = () => {
    return useQuery(
        [GET_ORGANIZERS_QUERY_KEY],
        async () => {
            return await organizerClient.all();
        }
    )
};