import {AttendeeTicket} from "../../../common/AttendeeTicket";
import {Product} from "../../../../types.ts";
import {PoweredByFooter} from "../../../common/PoweredByFooter";
import {useParams} from "react-router";
import {useGetOrderPublic} from "../../../../queries/useGetOrderPublic.ts";
import {t} from "@lingui/macro";
import {useEffect} from "react";
import classes from './PrintOrder.module.scss';

export const PrintOrder = () => {
    const {eventId, orderShortId} = useParams();
    const {data: order} = useGetOrderPublic(eventId, orderShortId, ['event']);
    const event = order?.event;

    useEffect(() => {
        if (order && event) {
            setTimeout(() => window?.print(), 500);
        }
    }, [order, event]);

    if (!order || !event) {
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
            <h2 className={classes.title}>{t`Tickets for`} {event.title}</h2>
            {order.attendees?.map((attendee) => {
                return (
                    <div key={attendee.id} className={classes.ticketPage}>
                        <AttendeeTicket
                            attendee={attendee}
                            product={attendee.product as Product}
                            event={event}
                            hideButtons
                            showPoweredBy
                        />
                    </div>
                );
            })}
            
            {/* PoweredBy footer for web view only */}
            <div className={classes.webOnlyFooter}>
                <PoweredByFooter/>
            </div>
        </div>
    );
}

export default PrintOrder;
