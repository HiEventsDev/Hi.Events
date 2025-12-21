import {useParams} from 'react-router';
import {useGetEventPublic} from '../../../../queries/useGetEventPublic.ts';
import {useGetAttendeePublic} from '../../../../queries/useGetAttendeePublic.ts';
import {AttendeeTicket} from '../../../common/AttendeeTicket';
import {Attendee, Product} from '../../../../types.ts';
import {PoweredByFooter} from '../../../common/PoweredByFooter';
import {useEffect} from "react";
import {OnlineEventDetails} from "../../../common/OnlineEventDetails";
import {t} from '@lingui/macro';
import classes from '../PrintOrder/PrintOrder.module.scss';

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
     * (c) Hi.Events Ltd 2025
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
        <div className={classes.container}>
            <h2 className={classes.title}>{t`Ticket for`} {event.title}</h2>
            <div className={classes.ticketPage}>
                <AttendeeTicket
                    attendee={attendee as Attendee}
                    product={attendee.product as Product}
                    event={event}
                    hideButtons
                />

                {(event?.settings?.is_online_event && (
                    <div style={{ marginTop: '32px', maxWidth: '900px', width: '100%' }}>
                        <OnlineEventDetails eventSettings={event.settings}/>
                    </div>
                ))}
                
                <div className={classes.poweredBy}>
                    <PoweredByFooter/>
                </div>
            </div>
            
            {/* PoweredBy footer for web view only */}
            <div className={classes.webOnlyFooter}>
                <PoweredByFooter/>
            </div>
        </div>
    )
}

export default PrintProduct;
