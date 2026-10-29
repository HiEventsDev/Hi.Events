import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {publicBoxOfficeClient} from "../api/box-office-public.client.ts";

export const GET_BOX_OFFICE_PUBLIC_QUERY_KEY = 'getBoxOfficePublic';

export const useGetBoxOfficePublic = (boxOfficeShortId: IdParam) => {
    return useQuery({
        queryKey: [GET_BOX_OFFICE_PUBLIC_QUERY_KEY, boxOfficeShortId],
        queryFn: async () => await publicBoxOfficeClient.get(boxOfficeShortId),
        retry: false,
    });
};
