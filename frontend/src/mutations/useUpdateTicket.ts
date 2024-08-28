import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam, Ticket} from "../types.ts";
import {ticketClient} from "../api/ticket.client.ts";
import {GET_TICKETS_QUERY_KEY} from "../queries/useGetTickets.ts";

export const useUpdateTicket = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({ticketData, ticketId, eventId}: {
            ticketData: Ticket,
            ticketId: IdParam,
            eventId: IdParam
        }) => ticketClient.update(eventId, ticketId, ticketData),

        onSuccess: () => {
            queryClient.invalidateQueries({queryKey: [GET_TICKETS_QUERY_KEY]});
        }
    });
}