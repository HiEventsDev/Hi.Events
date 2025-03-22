import {useQuery} from "@tanstack/react-query";
import {orderClientPublic} from "../api/order.client.ts";
import {IdParam} from "../types.ts";
import {getSessionIdentifier} from "../utilites/sessionIdentifier.ts";

export const GET_INITIATE_STRIPE_SESSION_PUBLIC_QUERY_KEY = 'getStripSessionPublic';

export const useCreateStripePaymentIntent = (eventId: IdParam, orderShortId: IdParam) => {
    return useQuery({
        queryKey: [GET_INITIATE_STRIPE_SESSION_PUBLIC_QUERY_KEY],

        queryFn: async () => {
            const {client_secret, account_id} = await orderClientPublic.createStripePaymentIntent(
                Number(eventId),
                String(orderShortId),
            );
            return {client_secret, account_id};
        },

        retry: false,
        staleTime: 0,
        gcTime: 0
    });
}
