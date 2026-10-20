import {useGetEventPublic} from "../../../../queries/useGetEventPublic.ts";
import {useParams} from "react-router";
import {useGetAttendeePublic} from "../../../../queries/useGetAttendeePublic.ts";
import {AttendeeTicket} from "../../../common/AttendeeTicket";
import {Attendee, Product} from "../../../../types.ts";
import {Container} from "@mantine/core";
import {t} from "@lingui/macro";
import {PoweredByFooter} from "../../../common/PoweredByFooter";
import {OnlineEventDetails} from "../../../common/OnlineEventDetails";
import {HomepageInfoMessage} from "../../../common/HomepageInfoMessage";
import classes from './AttendeeProductAndInformation.module.scss';

export const AttendeeProductAndInformation = () => {
    const {eventId, attendeeShortId} = useParams();
    const {data: event, isError: eventError} = useGetEventPublic(eventId);
    const {data: attendee, isError: attendeeError} = useGetAttendeePublic(eventId, String(attendeeShortId));

    if (eventError || attendeeError) {
        return (
            <HomepageInfoMessage
                status="not_found"
                message={t`Ticket Not Found`}
                subtitle={t`We couldn't find the ticket you're looking for. The link may have expired or the ticket details may have changed.`}
            />
        );
    }

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
        <Container>
            <h2 className={classes.title}>{t`Your ticket for`} {event.title}</h2>

            <AttendeeTicket
                attendee={attendee as Attendee}
                product={attendee.product as Product}
                event={event}
            />

            <OnlineEventDetails event={event} occurrence={attendee?.event_occurrence ?? null}/>

            <PoweredByFooter/>
        </Container>
    )
}

export default AttendeeProductAndInformation;
