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
     * (c) Hi.Events Ltd 2024-present
     *
     * Hi.Events is licensed under the GNU Affero General Public License (AGPL) version 3.
     * The full licence text is in the LICENCE file in the repository root.
     *
     * Under Section 7(b) of the AGPL, the "Powered by Hi.Events" notice must stay on all web pages
     * and emails. If you modify Hi.Events you may rephrase it, for example "Powered by [Your Company]
     * based on Hi.Events", but it must still link to https://hi.events.
     *
     * The notice must stay clearly visible and legible. Do not hide or obscure it, for example by
     * shrinking its font size, lowering its contrast, matching its colour to the background, covering
     * it or moving it off-screen.
     *
     * To remove the notice you need a commercial licence: https://hi.events/licensing
     * With a licence, hide it through your licence key or configuration rather than by editing this code.
     *
     * Commercial licences help keep Hi.Events free and open source. To keep that fair for everyone who
     * pays, we may work with a third-party compliance partner to find installations that remove or
     * obscure this notice without a licence. If you hear from us or them, it will start as a friendly
     * conversation, and you'll have 30 days to get a licence or restore the notice.
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

                <div style={{ marginTop: '32px', maxWidth: '900px', width: '100%' }}>
                    <OnlineEventDetails event={event} occurrence={attendee.event_occurrence ?? null}/>
                </div>
                
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
