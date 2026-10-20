import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {lineupClient, Profile} from "../api/lineup.client.ts";

export const GET_PROFILES_QUERY_KEY = 'getProfiles';

export const useGetProfiles = (organizerId: IdParam | undefined) => {
    return useQuery<Profile[]>({
        queryKey: [GET_PROFILES_QUERY_KEY, String(organizerId)],
        queryFn: async () => {
            const {data} = await lineupClient.getProfiles(organizerId as IdParam);
            return data;
        },
        enabled: !!organizerId,
    });
};
