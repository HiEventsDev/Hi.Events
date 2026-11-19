import {keepPreviousData, useQuery} from "@tanstack/react-query";
import {IdParam, QueryFilters} from "../../../types.ts";
import {crmClient} from "../api/crm.client.ts";
import {CrmSegmentReference} from "../types.ts";
import {crmKeys} from "./crmQueryKeys.ts";
import {retryUnlessRejected} from "./retryUnlessRejected.ts";

export const useGetCrmSuppressedContacts = (organizerId: IdParam | undefined, pagination: QueryFilters, segment: CrmSegmentReference, enabled = true) => useQuery({
    queryKey: crmKeys.suppressedList(organizerId, pagination, segment),
    queryFn: async () => await crmClient.suppressedContacts(organizerId, pagination, segment),
    enabled: !!organizerId && enabled,
    placeholderData: keepPreviousData,
    retry: retryUnlessRejected,
});

export const useGetCrmSuppressionSummary = (organizerId: IdParam | undefined, enabled = true) => useQuery({
    queryKey: crmKeys.suppressionSummary(organizerId),
    queryFn: async () => (await crmClient.suppressionSummary(organizerId)).data,
    enabled: !!organizerId && enabled,
});
