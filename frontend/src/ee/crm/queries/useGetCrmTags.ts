import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {crmClient} from "../api/crm.client.ts";
import {crmKeys} from "./crmQueryKeys.ts";

export const useGetCrmTags = (organizerId: IdParam | undefined) => {
    return useQuery({
        queryKey: crmKeys.tags(organizerId),
        queryFn: async () => {
            const {data} = await crmClient.tags(organizerId);
            return data;
        },
        enabled: !!organizerId,
    });
};
