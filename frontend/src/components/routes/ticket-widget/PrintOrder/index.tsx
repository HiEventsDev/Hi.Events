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

    /*
/**
 * (c) Hi.Events Ltd 2024
 *
 * PLEASE NOTE:
 *
 * Hi.Events is licensed under the GNU Affero General Public License (AGPL) version 3.
 *
 * You can find the full license text at: https://github.com/HiEventsDev/hi.events/blob/main/LICENSE
 *
 * In accordance with Section 7(b) of the AGPL, we ask that you retain the "Powered by Hi.Events" notice.
 *
 * If you wish to remove this notice, a commercial license is available at: https://hi.events/licensing
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