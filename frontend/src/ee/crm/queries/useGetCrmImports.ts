import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {crmClient} from "../api/crm.client.ts";
import {crmKeys} from "./crmQueryKeys.ts";

const POLL_WHILE_IMPORTING_MS = 3000;

export const useGetCrmImports = (organizerId: IdParam | undefined, enabled = true) => {
    return useQuery({
        queryKey: crmKeys.imports(organizerId),
        queryFn: async () => {
            const {data} = await crmClient.imports(organizerId);
            return data;
        },
        enabled: !!organizerId && enabled,
        refetchInterval: query => query.state.data?.some(entry => entry.status === 'PROCESSING') ? POLL_WHILE_IMPORTING_MS : false,
    });
};
