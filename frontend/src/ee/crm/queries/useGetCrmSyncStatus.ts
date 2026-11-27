import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {crmClient} from "../api/crm.client.ts";
import {crmKeys} from "./crmQueryKeys.ts";

export const useGetCrmSyncStatus = (organizerId: IdParam | undefined, enabled = true) => {
    return useQuery({
        queryKey: crmKeys.sync(organizerId),
        queryFn: async () => {
            const {data} = await crmClient.syncStatus(organizerId);
            return data;
        },
        enabled: !!organizerId && enabled,
        refetchInterval: (query) => query.state.data?.status === 'SYNCING' ? 5000 : false,
    });
};
