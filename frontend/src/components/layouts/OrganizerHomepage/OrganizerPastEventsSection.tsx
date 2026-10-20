import {t} from "@lingui/macro";
import {Event, HomepageSection, Organizer} from "../../../types.ts";
import {PAST_EVENTS_PREVIEW_FILTERS, useGetOrganizerPublicEvents} from "../../../queries/useGetOrganizerEventsPublic.ts";
import {HomepageSectionClasses} from "../EventHomepage/Lineup";
import {SectionShell} from "../EventHomepage/Sections/SectionShell.tsx";
import {EventCard} from "./EventCard";
import classes from "./OrganizerHomepage.module.scss";

interface OrganizerPastEventsSectionProps {
    organizer: Organizer;
    section: HomepageSection;
    sectionClasses: HomepageSectionClasses;
    onViewAll: () => void;
}

export const OrganizerPastEventsSection = ({organizer, section, sectionClasses, onViewAll}: OrganizerPastEventsSectionProps) => {
    const {data} = useGetOrganizerPublicEvents(organizer.id!, PAST_EVENTS_PREVIEW_FILTERS);
    const events = data?.data ?? [];

    if (events.length === 0) {
        return null;
    }

    return (
        <SectionShell section={section} sectionClasses={sectionClasses} fallbackHeading={t`Past events`}>
            <div className={classes.eventsContainer}>
                {events.map(event => <EventCard key={event.id} event={event as Event}/>)}
            </div>
            {data && data.meta.total > events.length && (
                <div className={classes.pastEventsFooter}>
                    <button className={classes.noEventsLink} onClick={onViewAll}>
                        {t`View all past events`}
                    </button>
                </div>
            )}
        </SectionShell>
    );
};
