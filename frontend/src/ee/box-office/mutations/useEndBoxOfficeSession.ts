import {useMutation} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {publicBoxOfficeClient} from "../api/box-office-public.client.ts";

export const useEndBoxOfficeSession = () => {
    return useMutation({
        mutationFn: ({boxOfficeShortId, token}: { boxOfficeShortId: IdParam; token: string }) => publicBoxOfficeClient.endSession(boxOfficeShortId, token),
    });
}
