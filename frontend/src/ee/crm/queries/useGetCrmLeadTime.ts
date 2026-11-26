import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {crmClient} from "../api/crm.client.ts";
import {crmKeys} from "./crmQueryKeys.ts";

export const useGetCrmLeadTime = (organizerId: IdParam | undefined, enabled = true) => {
    return useQuery({
        queryKey: crmKeys.leadTime(organizerId),
        queryFn: async () => {
            const {data} = await crmClient.leadTime(organizerId);
            return data;
        },
        enabled: !!organizerId && enabled,
    });
};
