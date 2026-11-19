import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {crmClient} from "../api/crm.client.ts";
import {crmKeys} from "./crmQueryKeys.ts";

export const useGetCrmOverview = (organizerId: IdParam | undefined, enabled = true) => {
    return useQuery({
        queryKey: crmKeys.overview(organizerId),
        queryFn: async () => {
            const {data} = await crmClient.overview(organizerId);
            return data;
        },
        enabled: !!organizerId && enabled,
    });
};
