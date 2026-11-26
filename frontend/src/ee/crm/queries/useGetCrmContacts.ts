import {keepPreviousData, useQuery} from "@tanstack/react-query";
import {IdParam, QueryFilters} from "../../../types.ts";
import {crmClient} from "../api/crm.client.ts";
import {CrmSegmentReference} from "../types.ts";
import {crmKeys} from "./crmQueryKeys.ts";
import {retryUnlessRejected} from "./retryUnlessRejected.ts";

export const useGetCrmContacts = (organizerId: IdParam | undefined, pagination: QueryFilters, segment?: CrmSegmentReference, enabled = true) => {
    return useQuery({
        queryKey: crmKeys.contactList(organizerId, pagination, segment),
        queryFn: async () => {
            return await crmClient.contacts(organizerId, pagination, segment);
        },
        enabled: !!organizerId && enabled,
        placeholderData: keepPreviousData,
        retry: retryUnlessRejected,
    });
};
