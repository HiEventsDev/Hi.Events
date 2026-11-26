import {QueryClient} from "@tanstack/react-query";
import {GET_TICKET_RESALE_LISTINGS_QUERY_KEY} from "../queries/useGetTicketResaleListings.ts";
import {GET_ATTENDEES_QUERY_KEY} from "../queries/useGetAttendees.ts";
import {GET_ATTENDEE_QUERY_KEY} from "../queries/useGetAttendee.ts";
import {GET_EVENT_ORDERS_QUERY_KEY} from "../queries/useGetEventOrders.ts";
import {GET_ORDER_QUERY_KEY} from "../queries/useGetOrder.ts";
import {GET_TICKET_HISTORY_QUERY_KEY} from "../queries/useGetTicketHistory.ts";

export const invalidateTicketResaleQueries = (queryClient: QueryClient) => Promise.all([
    GET_TICKET_RESALE_LISTINGS_QUERY_KEY,
    GET_ATTENDEES_QUERY_KEY,
    GET_ATTENDEE_QUERY_KEY,
    GET_EVENT_ORDERS_QUERY_KEY,
    GET_ORDER_QUERY_KEY,
    GET_TICKET_HISTORY_QUERY_KEY,
].map(queryKey => queryClient.invalidateQueries({queryKey: [queryKey]})));
