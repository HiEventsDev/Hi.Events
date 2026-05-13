import {useQuery, UseQueryOptions} from "@tanstack/react-query";
import {IdParam, OrganizerStripeConnectAccountsResponse} from "../types.ts";
import {organizerStripeClient} from "../api/organizer-stripe.client.ts";

export const GET_ORGANIZER_STRIPE_CONNECT_QUERY_KEY = "getOrganizerStripeConnect";

export const useGetOrganizerStripeConnect = (
    organizerId: IdParam,
    options?: Partial<UseQueryOptions<OrganizerStripeConnectAccountsResponse>>,
) => {
    return useQuery<OrganizerStripeConnectAccountsResponse>({
        queryKey: [GET_ORGANIZER_STRIPE_CONNECT_QUERY_KEY, organizerId],
        queryFn: async () => {
            const {data} = await organizerStripeClient.getConnectAccounts(organizerId);
            return data;
        },
        ...options,
    });
};
