import {Event, Ticket, TicketPrice} from "../../../types.ts";
import {t} from "@lingui/macro";
import {Tooltip} from "@mantine/core";
import {prettyDate, relativeDate} from "../../../utilites/dates.ts";
import {IconInfoCircle} from "@tabler/icons-react";

const TicketPriceSaleDateMessage = ({price, event}: { price: TicketPrice, event: Event }) => {
    if (price.is_sold_out) {
        return t`Sold out`;
    }

    if (price.is_after_sale_end_date) {
        return t`Sales ended`;
    }

    if (price.is_before_sale_start_date) {
        return (
            <span>
                {t`Sales start`}{' '}
                <Tooltip label={prettyDate(String(price.sale_start_date), event.timezone)}>
                    <span>{relativeDate(String(price.sale_start_date))}{' '}<IconInfoCircle size={12}/></span>
                </Tooltip>
            </span>
        );
    }

    return t`Not available`;
}

export const TicketAvailabilityMessage = ({ticket, event}: { ticket: Ticket, event: Event }) => {
    if (ticket.is_sold_out) {
        return t`Sold out`;
    }
    if (ticket.is_after_sale_end_date) {
        return t`Sales ended`;
    }
    if (ticket.is_before_sale_start_date) {
        return (
            <span>
                {t`Sales start`}{' '}
                <Tooltip label={prettyDate(String(ticket.sale_start_date), event.timezone)}>
                    <span>{relativeDate(String(ticket.sale_start_date))}{' '}<IconInfoCircle size={12}/></span>
                </Tooltip>
            </span>
        );
    }

    return t`Not available`;
}

interface TicketAndPriceAvailabilityProps {
    ticket: Ticket;
    price: TicketPrice;
    event: Event;
}

export const TicketPriceAvailability = ({ticket, price, event}: TicketAndPriceAvailabilityProps) => {

    if (ticket.type === 'TIERED') {
        return <TicketPriceSaleDateMessage price={price} event={event}/>
    }

    return <TicketAvailabilityMessage ticket={ticket} event={event}/>
}
