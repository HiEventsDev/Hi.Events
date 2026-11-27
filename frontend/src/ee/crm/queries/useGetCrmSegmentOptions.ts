import {keepPreviousData, useQuery} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {CrmSegmentOptionsParams} from "../types.ts";
import {crmClient} from "../api/crm.client.ts";
import {crmKeys} from "./crmQueryKeys.ts";

export const useGetCrmSegmentOptions = (organizerId: IdParam | undefined, params: CrmSegmentOptionsParams = {}, enabled = true) => {
    return useQuery({
        queryKey: crmKeys.segmentOptions(organizerId, params),
        queryFn: async () => {
            const {data} = await crmClient.segmentOptions(organizerId, params);
            return data;
        },
        enabled: !!organizerId && enabled,
        placeholderData: keepPreviousData,
        staleTime: 60_000,
    });
};
