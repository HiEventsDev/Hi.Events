import {useQuery} from "@tanstack/react-query";
import {GenericPaginatedResponse, IdParam, Message, QueryFilters} from "../types.ts";
import {messagesClient} from "../api/messages.client.ts";

export const GET_EVENT_MESSAGES_QUERY_KEY = 'getEventMessages';

export const useGetEventMessages = (eventId: IdParam, pagination: QueryFilters) => {
    return useQuery<GenericPaginatedResponse<Message>>({
            queryKey: [GET_EVENT_MESSAGES_QUERY_KEY, eventId, pagination],
            queryFn: async () => await messagesClient.all(eventId, pagination),
        }
    )
};
