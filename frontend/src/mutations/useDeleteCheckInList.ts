import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {checkInListClient} from "../api/check-in-list.client.ts";
import {GET_EVENT_CHECK_IN_LISTS_QUERY_KEY} from "../queries/useGetCheckInLists.ts";

export const useDeleteCheckInList = () => {
    const queryClient = useQueryClient();

    return useMutation(
        ({checkInListId, eventId}: {
            checkInListId: IdParam,
            eventId: IdParam,
        }) => checkInListClient.delete(eventId, checkInListId),
        {
            onSuccess: (_, {eventId}) => queryClient
                .invalidateQueries([GET_EVENT_CHECK_IN_LISTS_QUERY_KEY, eventId]),
        }
    )
}
