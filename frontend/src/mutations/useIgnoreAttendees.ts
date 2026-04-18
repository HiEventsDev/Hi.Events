import {useMutation, useQueryClient} from "@tanstack/react-query";
import {contactClient} from "../api/contact.client.ts";
import {GET_BACKFILL_UNLINKED_ATTENDEES_QUERY_KEY} from "../queries/useGetBackfillUnlinkedAttendees.ts";
import {GET_BACKFILL_SUMMARY_QUERY_KEY} from "../queries/useGetBackfillSummary.ts";
import {useGetMe} from "../queries/useGetMe.ts";

export const useIgnoreAttendees = () => {
    const queryClient = useQueryClient();
    const {data: me} = useGetMe();

    return useMutation({
        mutationFn: ({attendeeIds}: { attendeeIds: number[] }) =>
            contactClient.backfillIgnoreAttendees(me?.account_id, attendeeIds),

        onSuccess: () => {
            queryClient.invalidateQueries({queryKey: [GET_BACKFILL_UNLINKED_ATTENDEES_QUERY_KEY]});
            queryClient.invalidateQueries({queryKey: [GET_BACKFILL_SUMMARY_QUERY_KEY]});
        },
    });
};
