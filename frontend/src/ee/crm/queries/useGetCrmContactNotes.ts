import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {crmClient} from "../api/crm.client.ts";
import {crmKeys} from "./crmQueryKeys.ts";

export const useGetCrmContactNotes = (organizerId: IdParam | undefined, contactId: IdParam | undefined) => {
    return useQuery({
        queryKey: crmKeys.contactNotes(organizerId, contactId),
        queryFn: async () => {
            const {data} = await crmClient.contactNotes(organizerId, contactId);
            return data;
        },
        enabled: !!organizerId && !!contactId,
    });
};
