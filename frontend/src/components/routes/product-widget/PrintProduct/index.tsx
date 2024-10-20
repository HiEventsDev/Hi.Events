import {useParams} from 'react-router-dom';
import {useGetEventPublic} from '../../../../queries/useGetEventPublic.ts';
import {useGetAttendeePublic} from '../../../../queries/useGetAttendeePublic.ts';
import {AttendeeProduct} from '../../../common/AttendeeProduct';
import {Attendee, Product} from '../../../../types.ts';
import {Container} from '@mantine/core';
import {PoweredByFooter} from '../../../common/PoweredByFooter';
import {t} from '@lingui/macro';
import {useEffect} from "react";
import {OnlineEventDetails} from "../../../common/OnlineEventDetails";

const PrintProduct = () => {
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

    /**
     * (c) Hi.Events Ltd 2024
     *
     * PLEASE NOTE:
     *
     * Hi.Events is licensed under the GNU Affero General Public License (AGPL) version 3.
     *
     * You can find the full license text at: https://github.com/HiEventsDev/hi.events/blob/main/LICENCE
     *
     * In accordance with Section 7(b) of the AGPL, we ask that you retain the "Powered by Hi.Events" notice.
     *
     * If you wish to remove this notice, a commercial license is available at: https://hi.events/licensing
     */
    return (
        <Container>
            <h2>{t`Your ticket for`} {event.title}</h2>
            <AttendeeProduct
                attendee={attendee as Attendee}
                product={attendee.product as Product}
                event={event}
                hideButtons
            />

            {(event?.settings?.is_online_event && <OnlineEventDetails eventSettings={event.settings}/>)}

            <PoweredByFooter/>
        </Container>
    )
}

export default PrintProduct;
