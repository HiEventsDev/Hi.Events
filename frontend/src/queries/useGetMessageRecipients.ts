import {useQuery} from "@tanstack/react-query";
import {GenericPaginatedResponse, IdParam, OutgoingMessage, QueryFilters} from "../types.ts";
import {messagesClient} from "../api/messages.client.ts";

export const GET_MESSAGE_RECIPIENTS_QUERY_KEY = 'getMessageRecipients';

export const useGetMessageRecipients = (eventId: IdParam, messageId: IdParam, pagination: QueryFilters) => {
    return useQuery<GenericPaginatedResponse<OutgoingMessage>>({
        queryKey: [GET_MESSAGE_RECIPIENTS_QUERY_KEY, eventId, messageId, pagination],
        queryFn: async () => await messagesClient.recipients(eventId, messageId, pagination),
        enabled: !!eventId && !!messageId,
    });
};
