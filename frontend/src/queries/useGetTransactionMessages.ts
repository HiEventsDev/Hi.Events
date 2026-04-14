import {useQuery} from "@tanstack/react-query";
import {GenericPaginatedResponse, IdParam, OutgoingTransactionMessage, QueryFilters} from "../types.ts";
import {transactionMessagesClient} from "../api/transaction-messages.client.ts";

export const GET_TRANSACTION_MESSAGES_QUERY_KEY = 'getTransactionMessages';

export const useGetTransactionMessages = (eventId: IdParam, pagination: QueryFilters) => {
    return useQuery<GenericPaginatedResponse<OutgoingTransactionMessage>>({
        queryKey: [GET_TRANSACTION_MESSAGES_QUERY_KEY, eventId, pagination],
        queryFn: async () => await transactionMessagesClient.all(eventId, pagination),
        enabled: !!eventId,
    });
};
