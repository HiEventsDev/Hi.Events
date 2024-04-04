import {useParams} from 'react-router-dom';
import {useGetEventPublic} from '../../../../queries/useGetEventPublic.ts';
import {useGetAttendeePublic} from '../../../../queries/useGetAttendeePublic.ts';
import {AttendeeTicket} from '../../../common/AttendeeTicket';
import {Attendee, Ticket} from '../../../../types.ts';
import {Container} from '@mantine/core';
import {PoweredByFooter} from '../../../common/PoweredByFooter';
import {t} from '@lingui/macro';
import {useEffect} from "react";

const PrintTicket = () => {
    const {eventId, attendeeShortId} = useParams();
    const {data: event} = useGetEventPublic(eventId);
    const {data: attendee} = useGetAttendeePublic(eventId, String(attendeeShortId));

    useEffect(() => {
        if (attendee && event) {
            setTimeout(() => window?.print(), 500);
        }
    }, [attendee, event]);

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
                hideButtons
            />
            <PoweredByFooter/>
        </Container>
    )
}

export default PrintTicket;