import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {GET_EVENT_BOX_OFFICES_QUERY_KEY} from "../queries/useGetBoxOffices.ts";
import {boxOfficeClient} from "../api/box-office.client.ts";

export const useResetBoxOfficePin = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, boxOfficeId}: {
            eventId: IdParam,
            boxOfficeId: IdParam,
        }) => boxOfficeClient.resetPin(eventId, boxOfficeId),

        onSuccess: (_, variables) => queryClient
            .invalidateQueries({queryKey: [GET_EVENT_BOX_OFFICES_QUERY_KEY, variables.eventId]})
    });
}
