import {useQuery} from "@tanstack/react-query";
import {AxiosError} from "axios";
import {IdParam, OrganizerStripeConnectDetails} from "../types";
import {organizerStripeClient} from "../api/organizer-stripe.client";

export const GET_ORGANIZER_STRIPE_CONNECT_DETAILS = "getOrganizerStripeConnectDetails";

export const useCreateOrGetOrganizerStripeConnect = (
    organizerId: IdParam,
    enabled: boolean,
    platform?: string,
) => {
    return useQuery<OrganizerStripeConnectDetails, AxiosError>({
        queryKey: [GET_ORGANIZER_STRIPE_CONNECT_DETAILS, organizerId, platform],
        queryFn: async (): Promise<OrganizerStripeConnectDetails> => {
            const {data} = await organizerStripeClient.createOrGetConnectDetails(organizerId, platform);
            return data;
        },
        enabled,
        retry: false,
    });
};
