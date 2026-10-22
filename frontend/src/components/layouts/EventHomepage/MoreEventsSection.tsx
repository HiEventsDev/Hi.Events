import {Link} from "react-router";
import {t} from "@lingui/macro";
import {IconArrowRight} from "@tabler/icons-react";
import {Event, IdParam, Organizer} from "../../../types.ts";
import {UPCOMING_EVENTS_PREVIEW_FILTERS, useGetOrganizerPublicEvents} from "../../../queries/useGetOrganizerEventsPublic.ts";
import {organizerHomepagePath} from "../../../utilites/urlHelper.ts";
import {EventRows} from "../OrganizerHomepage/EventList";
import classes from "./EventHomepage.module.scss";

const MAX_EVENTS = 3;

interface MoreEventsSectionProps {
    heading?: string | null;
    organizer: Organizer;
    currentEventId: IdParam;
}

export const MoreEventsSection = ({heading, organizer, currentEventId}: MoreEventsSectionProps) => {
    const {data} = useGetOrganizerPublicEvents(organizer.id!, UPCOMING_EVENTS_PREVIEW_FILTERS);
    const events = (data?.data ?? [])
        .filter(event => String(event.id) !== String(currentEventId))
        .slice(0, MAX_EVENTS) as Event[];

    if (events.length === 0) {
        return null;
    }

    return (
        <section className={classes.section} id="more-events">
            <div className={classes.sectionHeader}>
                <h2 className={classes.sectionTitle}>{heading || t`More from ${organizer.name}`}</h2>
                <Link to={organizerHomepagePath(organizer)} className={classes.sectionLink}>
                    {t`View all`}
                    <IconArrowRight aria-hidden="true"/>
                </Link>
            </div>
            <EventRows events={events} titleAs="h3"/>
        </section>
    );
};
