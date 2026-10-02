import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {organizerStripeClient} from "../../../api/organizer-stripe.client.ts";
import {GET_ORGANIZER_TERMINAL_READERS_QUERY_KEY} from "../queries/useGetOrganizerTerminalReaders.ts";

export const useDeleteTerminalReader = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId, readerId}: {
            organizerId: IdParam,
            readerId: IdParam,
        }) => organizerStripeClient.deleteTerminalReader(organizerId, readerId),

        onSuccess: (_, variables) => queryClient.invalidateQueries({
            queryKey: [GET_ORGANIZER_TERMINAL_READERS_QUERY_KEY, variables.organizerId],
        }),
    });
}
