import {useMutation, useQueryClient} from "@tanstack/react-query";
import {CheckInListRequest, IdParam} from "../types.ts";
import {GET_EVENT_CHECK_IN_LISTS_QUERY_KEY} from "../queries/useGetCheckInLists.ts";
import {checkInListClient} from "../api/check-in-list.client.ts";
import {GET_EVENT_CHECK_IN_LIST_QUERY_KEY} from "../queries/useGetCheckInList.ts";

export const useEditCheckInList = () => {
    const queryClient = useQueryClient();

    return useMutation(
        ({checkInListData, eventId, checkInListId}: {
            eventId: IdParam,
            checkInListData: CheckInListRequest,
            checkInListId: IdParam,
        }) => checkInListClient.update(
            eventId,
            checkInListId,
            checkInListData,
        ),
        {
            onSuccess: (_, variables) => {
                queryClient.invalidateQueries({
                    queryKey: [
                        GET_EVENT_CHECK_IN_LIST_QUERY_KEY,
                        variables.eventId,
                        variables.checkInListId,
                    ]
                });
                return queryClient.invalidateQueries({queryKey: [GET_EVENT_CHECK_IN_LISTS_QUERY_KEY]});
            },
        }
    )
}
