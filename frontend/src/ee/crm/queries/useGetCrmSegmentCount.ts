import {keepPreviousData, useQuery} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {crmClient} from "../api/crm.client.ts";
import {CrmSegmentReference} from "../types.ts";
import {crmKeys} from "./crmQueryKeys.ts";

export const useGetCrmSegmentCount = (organizerId: IdParam | undefined, segment: CrmSegmentReference, enabled = true) => {
    return useQuery({
        queryKey: crmKeys.segmentCount(organizerId, segment),
        queryFn: async () => {
            const {data} = await crmClient.segmentCount(organizerId, segment);
            return data;
        },
        enabled: !!organizerId && enabled,
        placeholderData: keepPreviousData,
        retry: false,
    });
};
