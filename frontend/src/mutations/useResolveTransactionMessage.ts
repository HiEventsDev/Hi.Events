import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {transactionMessagesClient} from "../api/transaction-messages.client.ts";
import {GET_TRANSACTION_MESSAGE_FAILURES_QUERY_KEY} from "../queries/useGetTransactionMessageFailures.ts";

export const useResolveTransactionMessage = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, messageId}: { eventId: IdParam, messageId: IdParam }) =>
            transactionMessagesClient.resolve(eventId, messageId),
        onSuccess: () => {
            queryClient.invalidateQueries({queryKey: [GET_TRANSACTION_MESSAGE_FAILURES_QUERY_KEY]});
        },
    });
};
