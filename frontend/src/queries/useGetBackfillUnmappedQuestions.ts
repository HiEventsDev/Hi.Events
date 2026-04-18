import {useQuery} from "@tanstack/react-query";
import {GenericPaginatedResponse, IdParam, QueryFilters} from "../types.ts";
import {contactClient, ContactBackfillUnmappedQuestion} from "../api/contact.client.ts";
import {useGetMe} from "./useGetMe.ts";

export const GET_BACKFILL_UNMAPPED_QUESTIONS_QUERY_KEY = 'getBackfillUnmappedQuestions';

export const useGetBackfillUnmappedQuestions = (params: QueryFilters, includeIgnored = false) => {
    const meQuery = useGetMe();
    const accountId = meQuery.data?.account_id as IdParam;

    return useQuery<GenericPaginatedResponse<ContactBackfillUnmappedQuestion>>({
        queryKey: [GET_BACKFILL_UNMAPPED_QUESTIONS_QUERY_KEY, accountId, params, includeIgnored],
        enabled: meQuery.isFetched,
        queryFn: () => contactClient.backfillUnmappedQuestions(accountId, params, includeIgnored),
    });
};
