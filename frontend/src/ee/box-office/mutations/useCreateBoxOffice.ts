import {useMutation, useQueryClient} from "@tanstack/react-query";
import {BoxOfficeRequest, IdParam} from "../../../types.ts";
import {GET_EVENT_BOX_OFFICES_QUERY_KEY} from "../queries/useGetBoxOffices.ts";
import {boxOfficeClient} from "../api/box-office.client.ts";

export const useCreateBoxOffice = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({boxOfficeData, eventId}: {
            eventId: IdParam,
            boxOfficeData: BoxOfficeRequest,
        }) => boxOfficeClient.create(eventId, boxOfficeData),

        onSuccess: (_, variables) => queryClient
            .invalidateQueries({queryKey: [GET_EVENT_BOX_OFFICES_QUERY_KEY, variables.eventId]})
    });
}
