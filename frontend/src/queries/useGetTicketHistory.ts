import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {ticketHistoryClient} from "../api/ticket-history.client.ts";

export const GET_TICKET_HISTORY_QUERY_KEY = 'getTicketHistory';

export const useGetAttendeeHistory = (eventId: IdParam, attendeeId: IdParam) => useQuery({
    queryKey: [GET_TICKET_HISTORY_QUERY_KEY, eventId, 'attendee', attendeeId],
    queryFn: async () => (await ticketHistoryClient.forAttendee(eventId, attendeeId)).data,
    enabled: !!eventId && !!attendeeId,
});

export const useGetOrderHistory = (eventId: IdParam, orderId: IdParam) => useQuery({
    queryKey: [GET_TICKET_HISTORY_QUERY_KEY, eventId, 'order', orderId],
    queryFn: async () => (await ticketHistoryClient.forOrder(eventId, orderId)).data,
    enabled: !!eventId && !!orderId,
});
