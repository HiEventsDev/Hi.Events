import {useQuery} from "@tanstack/react-query";
import {GenericPaginatedResponse, IdParam, OutgoingMessage, QueryFilters} from "../types.ts";
import {outgoingMessagesClient} from "../api/outgoing-messages.client.ts";

export const GET_OUTGOING_MESSAGES_QUERY_KEY = 'getOutgoingMessages';

export const useGetOutgoingMessages = (eventId: IdParam, pagination: QueryFilters) => {
    return useQuery<GenericPaginatedResponse<OutgoingMessage>>({
        queryKey: [GET_OUTGOING_MESSAGES_QUERY_KEY, eventId, pagination],
        queryFn: async () => await outgoingMessagesClient.all(eventId, pagination),
        enabled: !!eventId,
    });
};
