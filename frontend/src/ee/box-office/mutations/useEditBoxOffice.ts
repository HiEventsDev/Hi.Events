import {useMutation, useQueryClient} from "@tanstack/react-query";
import {BoxOfficeRequest, IdParam} from "../../../types.ts";
import {GET_EVENT_BOX_OFFICES_QUERY_KEY} from "../queries/useGetBoxOffices.ts";
import {GET_BOX_OFFICE_QUERY_KEY} from "../queries/useGetBoxOffice.ts";
import {boxOfficeClient} from "../api/box-office.client.ts";

export const useEditBoxOffice = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({boxOfficeData, eventId, boxOfficeId}: {
            eventId: IdParam,
            boxOfficeId: IdParam,
            boxOfficeData: BoxOfficeRequest,
        }) => boxOfficeClient.update(eventId, boxOfficeId, boxOfficeData),

        onSuccess: (_, variables) => Promise.all([
            queryClient.invalidateQueries({queryKey: [GET_EVENT_BOX_OFFICES_QUERY_KEY, variables.eventId]}),
            queryClient.invalidateQueries({queryKey: [GET_BOX_OFFICE_QUERY_KEY, variables.eventId, variables.boxOfficeId]}),
        ]),
    });
}
