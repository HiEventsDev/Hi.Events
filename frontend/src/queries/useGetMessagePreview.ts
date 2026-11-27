import {keepPreviousData, useQuery} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {messagesClient, MessagePreviewRequest} from "../api/messages.client.ts";

export const GET_MESSAGE_PREVIEW_QUERY_KEY = 'getMessagePreview';

export const useGetMessagePreview = (eventId: IdParam, draft: MessagePreviewRequest, enabled = true) => {
    return useQuery({
        queryKey: [GET_MESSAGE_PREVIEW_QUERY_KEY, eventId, draft],
        queryFn: async () => {
            const {data} = await messagesClient.preview(eventId, draft);
            return data;
        },
        enabled: !!eventId && enabled,
        placeholderData: keepPreviousData,
        staleTime: 60_000,
        gcTime: 60_000,
        retry: false,
    });
};
