import {useMutation, useQueryClient} from "@tanstack/react-query";
import {contactClient} from "../api/contact.client.ts";
import {GET_BACKFILL_CONFLICTS_QUERY_KEY} from "../queries/useGetBackfillConflicts.ts";
import {GET_BACKFILL_SUMMARY_QUERY_KEY} from "../queries/useGetBackfillSummary.ts";
import {GET_CONTACTS_QUERY_KEY} from "../queries/useGetContacts.ts";
import {useGetMe} from "../queries/useGetMe.ts";

export const useApplyConflictDecisions = () => {
    const queryClient = useQueryClient();
    const {data: me} = useGetMe();

    return useMutation({
        mutationFn: ({decisions}: {
            decisions: Array<{ question_answer_id: number; decision: 'update' | 'leave_alone' }>;
        }) => contactClient.backfillApplyConflictDecisions(me?.account_id, decisions),

        onSuccess: () => {
            queryClient.invalidateQueries({queryKey: [GET_BACKFILL_CONFLICTS_QUERY_KEY]});
            queryClient.invalidateQueries({queryKey: [GET_BACKFILL_SUMMARY_QUERY_KEY]});
            queryClient.invalidateQueries({queryKey: [GET_CONTACTS_QUERY_KEY]});
        },
    });
};
