import {useQuery} from "@tanstack/react-query";
import {licenceClientPublic} from "../api/licence.client.ts";

export const GET_INSTANCE_INFO_QUERY_KEY = ['instance-info'];

export const useGetInstanceInfo = () => {
    return useQuery({
        queryKey: GET_INSTANCE_INFO_QUERY_KEY,
        queryFn: async () => (await licenceClientPublic.getInstanceInfo()).data,
        staleTime: 5 * 60 * 1000,
        refetchOnWindowFocus: false,
        retry: 1,
    });
};
