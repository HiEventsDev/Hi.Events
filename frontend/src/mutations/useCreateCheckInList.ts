import {useMutation, useQueryClient} from "@tanstack/react-query";
import {CheckInListRequest, IdParam} from "../types.ts";
import {GET_EVENT_CHECK_IN_LISTS_QUERY_KEY} from "../queries/useGetCheckInLists.ts";
import {checkInListClient} from "../api/check-in-list.client.ts";

export const useCreateCheckInList = () => {
    const queryClient = useQueryClient();

    return useMutation(
        ({checkInListData, eventId}: {
            eventId: IdParam,
            checkInListData: CheckInListRequest,
        }) => checkInListClient.create(eventId, checkInListData),
        {
            onSuccess: (_, variables) => queryClient
                .invalidateQueries({queryKey: [GET_EVENT_CHECK_IN_LISTS_QUERY_KEY, variables.eventId]}),
        }
    )
}
