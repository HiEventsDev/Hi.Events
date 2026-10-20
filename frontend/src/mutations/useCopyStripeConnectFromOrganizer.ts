import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {organizerStripeClient} from "../api/organizer-stripe.client.ts";
import {GET_ORGANIZER_STRIPE_CONNECT_QUERY_KEY} from "../queries/useGetOrganizerStripeConnect.ts";
import {GET_ORGANIZER_QUERY_KEY} from "../queries/useGetOrganizer.ts";

export const useCopyStripeConnectFromOrganizer = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId, sourceOrganizerId}: {
            organizerId: IdParam,
            sourceOrganizerId: IdParam,
        }) => organizerStripeClient.copyConnection(organizerId, sourceOrganizerId),
        onSuccess: (_, variables) => {
            return Promise.all([
                queryClient.invalidateQueries({queryKey: [GET_ORGANIZER_STRIPE_CONNECT_QUERY_KEY, variables.organizerId]}),
                queryClient.invalidateQueries({queryKey: [GET_ORGANIZER_QUERY_KEY, variables.organizerId]}),
            ]);
        },
    });
};
