import {useMutation} from "@tanstack/react-query";
import {IdParam, SortableItem} from "../types.ts";
import {ticketClient} from "../api/ticket.client.ts";

export const useSortTickets = () => {
    return useMutation(
        ({sortedTickets, eventId}: {
            eventId: IdParam,
            sortedTickets: SortableItem[],
        }) => ticketClient.sortTickets(eventId, sortedTickets),
    )
}