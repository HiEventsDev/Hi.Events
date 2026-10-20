import {useQuery} from "@tanstack/react-query";
import {locationClient} from "../api/location.client.ts";

export const GEO_STATUS_QUERY_KEY = "geoStatus";

export const useGeoStatus = () => {
    return useQuery({
        queryKey: [GEO_STATUS_QUERY_KEY],
        queryFn: () => locationClient.geoStatus(),
        staleTime: Infinity,
        gcTime: Infinity,
        refetchOnWindowFocus: false,
        refetchOnReconnect: false,
        refetchOnMount: false,
        retry: 1,
    });
};
