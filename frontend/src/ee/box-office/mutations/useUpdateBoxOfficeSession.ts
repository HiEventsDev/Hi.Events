import {useMutation} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {publicBoxOfficeClient} from "../api/box-office-public.client.ts";

export const useUpdateBoxOfficeSession = () => {
    return useMutation({
        mutationFn: ({boxOfficeShortId, payload}: {
            boxOfficeShortId: IdParam,
            payload: { event_occurrence_id?: number; stripe_terminal_reader_id?: number | null },
        }) => publicBoxOfficeClient.updateSession(boxOfficeShortId, payload),
    });
}
