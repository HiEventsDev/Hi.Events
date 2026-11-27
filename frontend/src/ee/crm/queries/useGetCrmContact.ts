import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {crmClient} from "../api/crm.client.ts";
import {crmKeys} from "./crmQueryKeys.ts";
import {retryUnlessRejected} from "./retryUnlessRejected.ts";

export const useGetCrmContact = (organizerId: IdParam | undefined, contactId: IdParam | undefined) => {
    return useQuery({
        queryKey: crmKeys.contact(organizerId, contactId),
        queryFn: async () => {
            const {data} = await crmClient.contact(organizerId, contactId);
            return data;
        },
        enabled: !!organizerId && !!contactId,
        retry: retryUnlessRejected,
    });
};
