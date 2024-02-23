import {useGetEventPublic} from "../../../../queries/useGetEventPublic.ts";
import {useParams} from "react-router-dom";
import {useGetAttendeePublic} from "../../../../queries/useGetAttendeePublic.ts";
import {AttendeeTicket} from "../../../common/AttendeeTicket";
import {Attendee, Ticket} from "../../../../types.ts";

export const AttendeeTicketAndInformation = () => {
    const {eventId, attendeeShortId} = useParams();
    const {data: event} = useGetEventPublic(eventId);
    const {data: attendee} = useGetAttendeePublic(eventId, String(attendeeShortId));

    if (!event || !attendee) {
        return null;
    }

    return (
        <>
            <AttendeeTicket
                key={attendee.id}
                attendee={attendee as Attendee}
                ticket={attendee.ticket as Ticket}
                event={event}
            />
        </>
    )
}