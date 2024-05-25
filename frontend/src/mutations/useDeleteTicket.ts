import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {ticketClient} from "../api/ticket.client.ts";
import {GET_TICKETS_QUERY_KEY} from "../queries/useGetTickets.ts";

export const useDeleteTicket = () => {
    const queryClient = useQueryClient();

    return useMutation(
        ({ticketId, eventId}: {
            ticketId: IdParam,
            eventId: IdParam,
        }) => ticketClient.delete(eventId, ticketId),
        {
            onSuccess: () => queryClient.invalidateQueries([GET_TICKETS_QUERY_KEY]),
        }
    )
}
