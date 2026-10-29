import {Event, EventOccurrence} from "../../../types.ts";
import {EventLineupSection, EventScheduleSection} from "../../layouts/EventHomepage/Lineup";
import {CustomHomepageSection} from "../../layouts/EventHomepage/Sections";
import {CUSTOM_SECTION_TYPES, homepageSectionHeading, resolveHomepageSections} from "../../../utilites/homepageSections.ts";
import classes from "./WidgetSections.module.scss";

const widgetSectionClasses = {
    section: classes.section,
    sectionHeader: classes.sectionHeader,
    sectionTitle: classes.sectionTitle,
};

interface HeavySectionProps {
    section: 'lineup' | 'schedule' | 'custom';
    event: Event;
    occurrence?: EventOccurrence;
}

export const HeavySection = ({section, event, occurrence}: HeavySectionProps) => {
    if (section === 'lineup') {
        const lineupEnabled = resolveHomepageSections(event.settings?.homepage_sections)
            .some(candidate => candidate.type === 'LINEUP' && candidate.enabled);
        if (!lineupEnabled) {
            return null;
        }
        return <EventLineupSection event={event} occurrence={occurrence} heading={homepageSectionHeading(event.settings?.homepage_sections, 'LINEUP', '') || null} sectionClasses={widgetSectionClasses} compact/>;
    }
    if (section === 'schedule') {
        return <EventScheduleSection event={event} occurrence={occurrence} heading={homepageSectionHeading(event.settings?.homepage_sections, 'SCHEDULE', '') || null} sectionClasses={widgetSectionClasses}/>;
    }

    const sections = resolveHomepageSections(event.settings?.homepage_sections)
        .filter(candidate => candidate.enabled && CUSTOM_SECTION_TYPES.includes(candidate.type));

    return (
        <>
            {sections.map(candidate => (
                <CustomHomepageSection
                    key={candidate.id}
                    images={event.images ?? []}
                    section={candidate}
                    sectionClasses={widgetSectionClasses}
                />
            ))}
        </>
    );
};
