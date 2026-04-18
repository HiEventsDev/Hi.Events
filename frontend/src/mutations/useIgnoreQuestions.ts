import {useMutation, useQueryClient} from "@tanstack/react-query";
import {contactClient} from "../api/contact.client.ts";
import {GET_BACKFILL_UNMAPPED_QUESTIONS_QUERY_KEY} from "../queries/useGetBackfillUnmappedQuestions.ts";
import {GET_BACKFILL_SUMMARY_QUERY_KEY} from "../queries/useGetBackfillSummary.ts";
import {useGetMe} from "../queries/useGetMe.ts";

export const useIgnoreQuestions = () => {
    const queryClient = useQueryClient();
    const {data: me} = useGetMe();

    return useMutation({
        mutationFn: ({questionIds}: { questionIds: number[] }) =>
            contactClient.backfillIgnoreQuestions(me?.account_id, questionIds),

        onSuccess: () => {
            queryClient.invalidateQueries({queryKey: [GET_BACKFILL_UNMAPPED_QUESTIONS_QUERY_KEY]});
            queryClient.invalidateQueries({queryKey: [GET_BACKFILL_SUMMARY_QUERY_KEY]});
        },
    });
};
