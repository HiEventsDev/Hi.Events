import {useMutation, useQueryClient} from "@tanstack/react-query";
import {contactClient} from "../api/contact.client.ts";
import {GET_BACKFILL_UNMAPPED_QUESTIONS_QUERY_KEY} from "../queries/useGetBackfillUnmappedQuestions.ts";
import {GET_BACKFILL_SUMMARY_QUERY_KEY} from "../queries/useGetBackfillSummary.ts";
import {GET_CONTACT_ATTRIBUTE_DEFINITIONS_QUERY_KEY} from "../queries/useGetContactAttributeDefinitions.ts";
import {useGetMe} from "../queries/useGetMe.ts";

export const useReuseQuestions = () => {
    const queryClient = useQueryClient();
    const {data: me} = useGetMe();

    return useMutation({
        mutationFn: ({questionIds}: { questionIds: number[] }) =>
            contactClient.backfillReuseQuestions(me?.account_id, questionIds),

        onSuccess: () => {
            queryClient.invalidateQueries({queryKey: [GET_BACKFILL_UNMAPPED_QUESTIONS_QUERY_KEY]});
            queryClient.invalidateQueries({queryKey: [GET_BACKFILL_SUMMARY_QUERY_KEY]});
            queryClient.invalidateQueries({queryKey: [GET_CONTACT_ATTRIBUTE_DEFINITIONS_QUERY_KEY]});
        },
    });
};
