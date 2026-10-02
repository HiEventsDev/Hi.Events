import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {organizerStripeClient} from "../../../api/organizer-stripe.client.ts";

export const GET_ORGANIZER_TERMINAL_READERS_QUERY_KEY = 'getOrganizerTerminalReaders';

export const useGetOrganizerTerminalReaders = (organizerId: IdParam, enabled = true) => {
    return useQuery({
        queryKey: [GET_ORGANIZER_TERMINAL_READERS_QUERY_KEY, organizerId],
        queryFn: async () => {
            const {data} = await organizerStripeClient.listTerminalReaders(organizerId);
            return data;
        },
        enabled,
        refetchInterval: 30000,
    });
};
