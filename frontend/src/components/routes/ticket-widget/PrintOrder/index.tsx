import {AttendeeTicket} from "../../../common/AttendeeTicket";
import {Ticket} from "../../../../types.ts";
import {PoweredByFooter} from "../../../common/PoweredByFooter";
import {useParams} from "react-router-dom";
import {useGetOrderPublic} from "../../../../queries/useGetOrderPublic.ts";
import {useGetEventPublic} from "../../../../queries/useGetEventPublic.ts";
import {t} from "@lingui/macro";
import {Container} from "@mantine/core";
import {useEffect} from "react";

export const PrintOrder = () => {
    const {eventId, orderShortId} = useParams();
    const {data: order} = useGetOrderPublic(eventId, orderShortId);
    const {data: event} = useGetEventPublic(eventId);

    useEffect(() => {
        if (order && event) {
            setTimeout(() => window?.print(), 500);
        }
    }, [order, event]);

    if (!order || !event) {
        return null;
    }

    /**
     * PLEASE NOTE:
     *
     * Under the terms of the license, you are not permitted to remove or obscure the powered by footer unless you have a white-label
     * or commercial license.
     * @see https://github.com/HiEventsDev/hi.events/blob/main/LICENCE#L13
     **
     * You can purchase a license at https://hi.events/licensing
     */
    return (
        <>
            <Container>
                <h2>{t`Tickets for`} {event.title}</h2>
                {order.attendees?.map((attendee) => {
                    return (
                        <AttendeeTicket
                            key={attendee.id}
                            attendee={attendee}
                            ticket={attendee.ticket as Ticket}
                            event={event}
                            hideButtons
                        />
                    );
                })}

                <PoweredByFooter/>
            </Container>
        </>
    );
}

export default PrintOrder;