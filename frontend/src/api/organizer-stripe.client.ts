import {api} from "./client.ts";
import {
    GenericDataResponse,
    IdParam,
    OrganizerStripeConnectAccountsResponse,
    OrganizerStripeConnectDetails,
} from "../types.ts";

export const organizerStripeClient = {
    createOrGetConnectDetails: async (organizerId: IdParam, platform?: string) => {
        const response = await api.post<GenericDataResponse<OrganizerStripeConnectDetails>>(
            `organizers/${organizerId}/stripe/connect`,
            {platform},
        );
        return response.data;
    },
    getConnectAccounts: async (organizerId: IdParam) => {
        const response = await api.get<GenericDataResponse<OrganizerStripeConnectAccountsResponse>>(
            `organizers/${organizerId}/stripe/connect_accounts`,
        );
        return response.data;
    },
    copyConnection: async (organizerId: IdParam, sourceOrganizerId: IdParam) => {
        const response = await api.post<GenericDataResponse<OrganizerStripeConnectDetails>>(
            `organizers/${organizerId}/stripe/copy_from/${sourceOrganizerId}`,
            {},
        );
        return response.data;
    },
    disconnectAccount: async (organizerId: IdParam, stripeAccountId: string) => {
        await api.delete(`organizers/${organizerId}/stripe/connect_accounts/${stripeAccountId}`);
    },
};
