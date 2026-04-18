import {useQuery} from "@tanstack/react-query";
import {GenericPaginatedResponse, IdParam, QueryFilters} from "../types.ts";
import {contactClient, ContactBackfillUnlinkedAttendee} from "../api/contact.client.ts";
import {useGetMe} from "./useGetMe.ts";

export const GET_BACKFILL_UNLINKED_ATTENDEES_QUERY_KEY = 'getBackfillUnlinkedAttendees';

export const useGetBackfillUnlinkedAttendees = (params: QueryFilters, includeProcessed = false) => {
    const meQuery = useGetMe();
    const accountId = meQuery.data?.account_id as IdParam;

    return useQuery<GenericPaginatedResponse<ContactBackfillUnlinkedAttendee>>({
        queryKey: [GET_BACKFILL_UNLINKED_ATTENDEES_QUERY_KEY, accountId, params, includeProcessed],
        enabled: meQuery.isFetched,
        queryFn: () => contactClient.backfillUnlinkedAttendees(accountId, params, includeProcessed),
    });
};
