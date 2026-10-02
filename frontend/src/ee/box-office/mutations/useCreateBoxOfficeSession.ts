import {useMutation} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {publicBoxOfficeClient} from "../api/box-office-public.client.ts";

export const useCreateBoxOfficeSession = () => {
    return useMutation({
        mutationFn: ({boxOfficeShortId, payload}: {
            boxOfficeShortId: IdParam,
            payload: { operator_name: string; pin: string | null; event_occurrence_id?: number | null; stripe_terminal_reader_id?: number | null },
        }) => publicBoxOfficeClient.createSession(boxOfficeShortId, payload),
    });
}
