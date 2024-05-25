import {Attendee, Ticket, TicketType} from "../types.ts";

export const getAttendeeTicketTitle = (attendee: Attendee): string => {
    const ticket = attendee.ticket as Ticket;
    if (ticket.type !== TicketType.Tiered) {
        return ticket.title;
    }

    const ticketPrice = ticket.prices
        ?.find(price => price.id === attendee.ticket_price_id);

    return ticket.title + (ticketPrice?.label ? ` - ${ticketPrice.label}` : '');
}

export const getAttendeeTicketPrice = (attendee: Attendee, ticket: Ticket): number => {
    const ticketPrice = ticket.prices
        ?.find(price => price.id === attendee.ticket_price_id);

    return ticketPrice?.price ?? 0;
}