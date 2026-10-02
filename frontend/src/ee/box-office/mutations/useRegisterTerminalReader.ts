import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {organizerStripeClient} from "../../../api/organizer-stripe.client.ts";
import {GET_ORGANIZER_TERMINAL_READERS_QUERY_KEY} from "../queries/useGetOrganizerTerminalReaders.ts";

export const useRegisterTerminalReader = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId, payload}: {
            organizerId: IdParam,
            payload: { registration_code: string; label: string },
        }) => organizerStripeClient.registerTerminalReader(organizerId, payload),

        onSuccess: (_, variables) => queryClient.invalidateQueries({
            queryKey: [GET_ORGANIZER_TERMINAL_READERS_QUERY_KEY, variables.organizerId],
        }),
    });
}
