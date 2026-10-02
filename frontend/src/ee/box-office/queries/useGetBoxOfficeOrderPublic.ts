import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {publicBoxOfficeClient} from "../api/box-office-public.client.ts";

export const GET_BOX_OFFICE_ORDER_PUBLIC_QUERY_KEY = 'getBoxOfficeOrderPublic';

export const useGetBoxOfficeOrderPublic = (boxOfficeShortId: IdParam, orderShortId: IdParam | null, poll: boolean) => {
    return useQuery({
        queryKey: [GET_BOX_OFFICE_ORDER_PUBLIC_QUERY_KEY, boxOfficeShortId, orderShortId],
        queryFn: async () => {
            const {data} = await publicBoxOfficeClient.getOrder(boxOfficeShortId, orderShortId as IdParam);
            return data;
        },
        enabled: !!orderShortId,
        refetchInterval: poll ? 2000 : false,
        refetchIntervalInBackground: true,
    });
};
