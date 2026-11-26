import {useInfiniteQuery} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {crmClient} from "../api/crm.client.ts";
import {crmKeys} from "./crmQueryKeys.ts";

export const useGetCrmContactConsent = (organizerId: IdParam | undefined, contactId: IdParam | undefined) => {
    return useInfiniteQuery({
        queryKey: crmKeys.contactConsent(organizerId, contactId),
        queryFn: async ({pageParam}) => crmClient.contactConsent(organizerId, contactId, pageParam),
        initialPageParam: 1,
        getNextPageParam: (lastPage) => lastPage.meta.current_page < lastPage.meta.last_page
            ? lastPage.meta.current_page + 1
            : undefined,
        enabled: !!organizerId && !!contactId,
    });
};
