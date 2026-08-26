import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {organizerStripeClient} from "../api/organizer-stripe.client.ts";
import {GET_ORGANIZER_STRIPE_CONNECT_QUERY_KEY} from "../queries/useGetOrganizerStripeConnect.ts";
import {GET_ORGANIZER_STRIPE_CONNECT_DETAILS} from "../queries/useCreateOrGetOrganizerStripeConnect.ts";
import {GET_ORGANIZER_QUERY_KEY} from "../queries/useGetOrganizer.ts";

export const useDisconnectOrganizerStripeConnect = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId, stripeAccountId}: {
            organizerId: IdParam,
            stripeAccountId: string,
        }) => organizerStripeClient.disconnectAccount(organizerId, stripeAccountId),
        onSuccess: (_, variables) => {
            queryClient.removeQueries({queryKey: [GET_ORGANIZER_STRIPE_CONNECT_DETAILS, variables.organizerId]});
            return Promise.all([
                queryClient.invalidateQueries({queryKey: [GET_ORGANIZER_STRIPE_CONNECT_QUERY_KEY, variables.organizerId]}),
                queryClient.invalidateQueries({queryKey: [GET_ORGANIZER_QUERY_KEY, variables.organizerId]}),
            ]);
        },
    });
};
