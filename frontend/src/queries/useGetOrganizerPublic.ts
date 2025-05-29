import {useQuery} from "@tanstack/react-query";
import {IdParam, Organizer} from "../types.ts";
import {AxiosError} from "axios";
import {organizerPublicClient} from "../api/organizer.client.ts";

export const GET_ORGANIZER_PUBLIC_QUERY_KEY = "getOrganizerPublic";

export const getOrganizerPublicQuery = (
    organizerId: IdParam,
) => ({
    queryKey: [GET_ORGANIZER_PUBLIC_QUERY_KEY, organizerId] as const,
    queryFn: async (): Promise<Organizer> => {
        const {data} = await organizerPublicClient.findByID(organizerId);
        return data;
    },
    refetchOnWindowFocus: false,
    retryOnMount: false,
    staleTime: 0,
    retry: false,
});

export const useGetOrganizerPublic = (organizerId: IdParam | undefined, options?: {enabled?: boolean}) => {
    return useQuery<Organizer, AxiosError>({
        ...getOrganizerPublicQuery(organizerId || ''),
        enabled: !!organizerId && (options?.enabled ?? true)
    });
};
