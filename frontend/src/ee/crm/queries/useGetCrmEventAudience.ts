import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {crmClient} from "../api/crm.client.ts";
import {crmKeys} from "./crmQueryKeys.ts";

export const useGetCrmEventAudience = (organizerId: IdParam | undefined, eventId: IdParam | undefined, enabled: boolean) => {
    return useQuery({
        queryKey: crmKeys.eventAudience(organizerId, eventId),
        queryFn: async () => {
            const {data} = await crmClient.eventAudience(organizerId, eventId);
            return data;
        },
        enabled: !!organizerId && !!eventId && enabled,
    });
};
