import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {crmClient} from "../api/crm.client.ts";
import {crmKeys} from "./crmQueryKeys.ts";

export const useGetCrmSegments = (organizerId: IdParam | undefined, enabled = true) => {
    return useQuery({
        queryKey: crmKeys.segments(organizerId),
        queryFn: async () => {
            return await crmClient.segments(organizerId);
        },
        enabled: !!organizerId && enabled,
    });
};
