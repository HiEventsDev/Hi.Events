import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {crmClient} from "../api/crm.client.ts";
import {crmKeys} from "./crmQueryKeys.ts";

export const useGetCrmRetention = (organizerId: IdParam | undefined, enabled = true) => {
    return useQuery({
        queryKey: crmKeys.retention(organizerId),
        queryFn: async () => {
            const {data} = await crmClient.retention(organizerId);
            return data;
        },
        enabled: !!organizerId && enabled,
    });
};
