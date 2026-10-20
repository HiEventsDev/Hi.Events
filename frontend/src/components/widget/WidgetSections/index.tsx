import {ReactNode} from "react";
import {t} from "@lingui/macro";
import {IconArrowUpRight, IconCalendar, IconMapPin, IconWorld} from "@tabler/icons-react";
import {Spoiler} from "@mantine/core";
import {Event, EventOccurrence, EventType, HomepageSectionType, OrganizerStatus} from "../../../types.ts";
import {UserGeneratedContent} from "../../common/UserGeneratedContent";
import {homepageSectionHeading} from "../../../utilites/homepageSections.ts";
import {createLazyModule} from "../../../utilites/lazyModule.ts";
import {buildEventLocationDisplay, summariseEventLocations} from "../../../utilites/effectiveLocation.ts";
import {summariseEventSchedule} from "../../../utilites/eventListing.ts";
import {eventScheduleLabel} from "../../../utilites/eventListingLabels.ts";
import {eventCoverImage, imageUrl, organizerHomepageUrl} from "../../../utilites/urlHelper.ts";
import {WidgetSection} from "../../../utilites/widgetConfig.ts";
import classes from "./WidgetSections.module.scss";

const heavySections = createLazyModule(() => import("./HeavySections.tsx"));

const HEAVY_SECTIONS: WidgetSection[] = ['lineup', 'schedule', 'custom'];

export const preloadHeavySections = (sections: WidgetSection[] | undefined) => {
    if (typeof window !== 'undefined' && sections?.some(section => HEAVY_SECTIONS.includes(section))) {
        heavySections.load().catch(() => undefined);
    }
};

export const WidgetSectionStack = ({children}: { children: ReactNode }) => (
    <div className={classes.stack}>{children}</div>
);

const SectionShell = ({title, children, testId}: { title: string; children: ReactNode; testId?: string }) => (
    <section className={classes.section} data-testid={testId}>
        <div className={classes.sectionHeader}>
            <h2 className={classes.sectionTitle}>{title}</h2>
        </div>
        {children}
    </section>
);

const sectionHeading = (event: Event, type: HomepageSectionType, fallback: string) =>
    homepageSectionHeading(event.settings?.homepage_sections, type, fallback);

export const WidgetEventHeader = ({event, occurrence, reserveCloseButton, compact}: {
    event: Event;
    occurrence?: EventOccurrence;
    reserveCloseButton?: boolean;
    compact?: boolean;
}) => {
    const cover = eventCoverImage(event);
    const schedule = summariseEventSchedule(event);
    const dateLabel = occurrence
        ? eventScheduleLabel({...schedule, start: occurrence.start_date, end: occurrence.end_date ?? null, isSeriesRange: false}, event.timezone)
        : eventScheduleLabel(schedule, event.timezone);
    const locations = summariseEventLocations(event);
    const display = locations.kind === 'single'
        ? buildEventLocationDisplay(event, locations.eventLocation, locations.isEventDefault)
        : null;
    const locationLabel = locations.kind === 'varied'
        ? (locations.types.length > 1 ? t`Online & in-person` : t`Multiple locations`)
        : display?.isOnline ? t`Online event` : (display?.venueName || display?.short);

    return (
        <header className={classes.header} data-compact={compact ? '' : undefined} data-testid="widget-event-header">
            {cover?.url && !compact && (
                <div className={classes.cover}>
                    <img src={cover.url} alt="" loading="lazy" decoding="async"/>
                </div>
            )}
            <h1 className={classes.title} data-reserve-close={reserveCloseButton ? '' : undefined}>{event.title}</h1>
            <div className={classes.meta}>
                <span className={classes.metaItem}>
                    <IconCalendar size={15} aria-hidden/>
                    {dateLabel}
                </span>
                {locationLabel && (
                    <span className={classes.metaItem}>
                        <IconMapPin size={15} aria-hidden/>
                        {locationLabel}
                    </span>
                )}
            </div>
        </header>
    );
};

const AboutSection = ({event}: { event: Event }) => {
    if (!event.description) {
        return null;
    }
    return (
        <SectionShell title={sectionHeading(event, 'ABOUT', t`About`)} testId="widget-about-section">
            <Spoiler maxHeight={180} showLabel={t`Read more`} hideLabel={t`Show less`} classNames={{control: classes.spoilerControl}}>
                <UserGeneratedContent className={classes.richText} html={event.description}/>
            </Spoiler>
        </SectionShell>
    );
};

const LocationSection = ({event}: { event: Event }) => {
    const locations = summariseEventLocations(event);
    if (locations.kind !== 'single') {
        return null;
    }
    const display = buildEventLocationDisplay(event, locations.eventLocation, locations.isEventDefault);
    if (!display || display.isOnline) {
        return null;
    }

    return (
        <SectionShell title={sectionHeading(event, 'LOCATION', t`Location`)} testId="widget-location-section">
            <div className={classes.card}>
                <div className={classes.cardIcon}><IconMapPin size={18} aria-hidden/></div>
                <div className={classes.cardBody}>
                    {display.venueName && <div className={classes.cardTitle}>{display.venueName}</div>}
                    {display.full && <div className={classes.cardText}>{display.full}</div>}
                    {display.mapsUrl && (
                        <a className={classes.link} href={display.mapsUrl} target="_blank" rel="nofollow ugc noopener noreferrer">
                            {t`Get directions`}
                            <IconArrowUpRight size={14} aria-hidden/>
                        </a>
                    )}
                </div>
            </div>
        </SectionShell>
    );
};

const OrganizerSection = ({event}: { event: Event }) => {
    const organizer = event.organizer;
    if (!organizer || organizer.status !== OrganizerStatus.LIVE) {
        return null;
    }
    const logo = imageUrl('ORGANIZER_LOGO', organizer.images);

    return (
        <SectionShell title={sectionHeading(event, 'ORGANIZER', t`Organizer`)} testId="widget-organizer-section">
            <div className={classes.card}>
                {logo
                    ? <img className={classes.avatar} src={logo} alt="" loading="lazy"/>
                    : <div className={classes.avatarPlaceholder} aria-hidden>{organizer.name.charAt(0).toUpperCase()}</div>}
                <div className={classes.cardBody}>
                    <div className={classes.cardTitle}>{organizer.name}</div>
                    <a className={classes.link} href={organizerHomepageUrl(organizer)} target="_blank" rel="noopener">
                        <IconWorld size={14} aria-hidden/>
                        {t`More events`}
                    </a>
                </div>
            </div>
        </SectionShell>
    );
};

const LazySection = ({section, event, occurrence}: {
    section: 'lineup' | 'schedule' | 'custom';
    event: Event;
    occurrence?: EventOccurrence;
}) => {
    const module = heavySections.useModule();
    return module ? <module.HeavySection section={section} event={event} occurrence={occurrence}/> : null;
};

interface WidgetEventSectionProps {
    section: Exclude<WidgetSection, 'tickets'>;
    event: Event;
    occurrence?: EventOccurrence;
    reserveCloseButton?: boolean;
}

export const WidgetEventSection = ({section, event, occurrence, reserveCloseButton}: WidgetEventSectionProps) => {
    const lineupOccurrence = event.type === EventType.RECURRING ? occurrence : undefined;

    switch (section) {
        case 'header':
            return <WidgetEventHeader event={event} occurrence={lineupOccurrence} reserveCloseButton={reserveCloseButton}/>;
        case 'about':
            return <AboutSection event={event}/>;
        case 'location':
            return <LocationSection event={event}/>;
        case 'organizer':
            return <OrganizerSection event={event}/>;
        case 'lineup':
        case 'schedule':
        case 'custom':
            return <LazySection section={section} event={event} occurrence={lineupOccurrence}/>;
    }
};
