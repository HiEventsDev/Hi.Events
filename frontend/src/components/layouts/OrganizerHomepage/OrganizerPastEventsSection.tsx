import {t} from "@lingui/macro";
import {Event, HomepageSection, Organizer} from "../../../types.ts";
import {PAST_EVENTS_PREVIEW_FILTERS, useGetOrganizerPublicEvents} from "../../../queries/useGetOrganizerEventsPublic.ts";
import {HomepageSectionClasses} from "../EventHomepage/Lineup";
import {SectionShell} from "../EventHomepage/Sections/SectionShell.tsx";
import {EventList} from "./EventList";
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
            <EventList events={events as Event[]} past/>
            {data && data.meta.total > events.length && (
                <div className={classes.pastEventsFooter}>
                    <button type="button" className={classes.textButton} onClick={onViewAll}>
                        {t`View all past events`}
                    </button>
                </div>
            )}
        </SectionShell>
    );
};
