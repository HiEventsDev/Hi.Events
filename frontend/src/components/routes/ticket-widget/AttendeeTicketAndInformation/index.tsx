import {useGetEventPublic} from "../../../../queries/useGetEventPublic.ts";
import {useParams} from "react-router-dom";
import {useGetAttendeePublic} from "../../../../queries/useGetAttendeePublic.ts";
import {AttendeeTicket} from "../../../common/AttendeeTicket";
import {Attendee, Ticket} from "../../../../types.ts";
import {Container} from "@mantine/core";
import {t} from "@lingui/macro";
import {PoweredByFooter} from "../../../common/PoweredByFooter";

export const AttendeeTicketAndInformation = () => {
    const {eventId, attendeeShortId} = useParams();
    const {data: event} = useGetEventPublic(eventId);
    const {data: attendee} = useGetAttendeePublic(eventId, String(attendeeShortId));

    if (!event || !attendee) {
        return null;
    }

    return (
        <Container>
            <h2>{t`Your ticket for`} {event.title}</h2>
            <AttendeeTicket
                attendee={attendee as Attendee}
                ticket={attendee.ticket as Ticket}
                event={event}
            />
            <PoweredByFooter/>
        </Container>
    )
}

export default AttendeeTicketAndInformation;