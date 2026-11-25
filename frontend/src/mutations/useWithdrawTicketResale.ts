import {useMutation, useQueryClient} from "@tanstack/react-query";
import {TicketResaleLink, ticketResaleClient} from "../api/ticket-resale.client.ts";
import {GET_ORDER_PUBLIC_QUERY_KEY} from "../queries/useGetOrderPublic.ts";
import {GET_ATTENDEE_PUBLIC_QUERY_KEY} from "../queries/useGetAttendeePublic.ts";

export const useWithdrawTicketResale = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({link}: { link: TicketResaleLink }) => ticketResaleClient.withdraw(link),

        onSuccess: () => Promise.all([
            queryClient.invalidateQueries({queryKey: [GET_ORDER_PUBLIC_QUERY_KEY]}),
            queryClient.invalidateQueries({queryKey: [GET_ATTENDEE_PUBLIC_QUERY_KEY]}),
        ]),
    });
};
