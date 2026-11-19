import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {crmClient} from "../api/crm.client.ts";
import {crmKeys} from "./crmQueryKeys.ts";

export const useGetCrmBulkOperation = (organizerId: IdParam | undefined, operationId: IdParam | undefined) => {
    return useQuery({
        queryKey: crmKeys.bulkOperation(organizerId, operationId),
        queryFn: async () => {
            const {data} = await crmClient.bulkOperation(organizerId, operationId);
            return data;
        },
        enabled: !!organizerId && !!operationId,
        refetchInterval: query => query.state.data?.status === 'RUNNING' ? 1000 : false,
    });
};
