import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {crmClient} from "../api/crm.client.ts";
import {crmKeys} from "./crmQueryKeys.ts";

export const useGetCrmSmsConnection = (organizerId: IdParam | undefined, enabled = true) => {
    return useQuery({
        queryKey: crmKeys.smsConnection(organizerId),
        queryFn: async () => {
            const {data} = await crmClient.smsConnection(organizerId!);
            return data;
        },
        enabled: !!organizerId && enabled,
    });
};
