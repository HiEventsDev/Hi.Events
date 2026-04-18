import {useQuery} from "@tanstack/react-query";
import {GenericPaginatedResponse, IdParam, QueryFilters} from "../types.ts";
import {contactClient, ContactBackfillConflictRow} from "../api/contact.client.ts";
import {useGetMe} from "./useGetMe.ts";

export const GET_BACKFILL_CONFLICTS_QUERY_KEY = 'getBackfillConflicts';

export const useGetBackfillConflicts = (params: QueryFilters, includeProcessed: boolean) => {
    const meQuery = useGetMe();
    const accountId = meQuery.data?.account_id as IdParam;

    return useQuery<GenericPaginatedResponse<ContactBackfillConflictRow>>({
        queryKey: [GET_BACKFILL_CONFLICTS_QUERY_KEY, accountId, params, includeProcessed],
        enabled: meQuery.isFetched,
        queryFn: () => contactClient.backfillConflicts(accountId, params, includeProcessed),
    });
};
