import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {contactClient, ContactBackfillSummary} from "../api/contact.client.ts";
import {useGetMe} from "./useGetMe.ts";

export const GET_BACKFILL_SUMMARY_QUERY_KEY = 'getBackfillSummary';

export const useGetBackfillSummary = () => {
    const meQuery = useGetMe();
    const accountId = meQuery.data?.account_id as IdParam;

    return useQuery<{ data: ContactBackfillSummary }>({
        queryKey: [GET_BACKFILL_SUMMARY_QUERY_KEY, accountId],
        enabled: meQuery.isFetched,
        queryFn: () => contactClient.backfillSummary(accountId),
    });
};
