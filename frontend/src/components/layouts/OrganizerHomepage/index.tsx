import {useLocation, useNavigate} from "react-router";
import {EventList, EventSpotlight} from './EventList';
import classes from './OrganizerHomepage.module.scss';
import {Fragment, useEffect, useRef, useState} from 'react';
import {Event, GenericPaginatedResponse, HomepageSection, Organizer} from "../../../types.ts";
import {OrganizerDocumentHead} from "../../common/OrganizerDocumentHead";
import {IconCalendarEvent, IconCalendarOff, IconMail, IconMapPin, IconShare2, IconWorld} from '@tabler/icons-react';
import {plural, t} from "@lingui/macro";
import {ContactOrganizerModal} from "../../common/ContactOrganizerModal";
import {getGoogleMapsUrl, getShortLocationDisplay} from "../../../utilites/addressUtilities.ts";
import {organizerHomepagePath, organizerHomepageUrl} from "../../../utilites/urlHelper.ts";
import {StatusToggle} from "../../common/StatusToggle";
import {Pagination} from "../../common/Pagination";
import {validateThemeSettings} from "../../../utilites/themeUtils.ts";
import {useOrganizerTrackingPixels} from "../../../hooks/useOrganizerTrackingPixels";
import {UserGeneratedContent} from "../../common/UserGeneratedContent";
import {ShareComponent} from "../../common/ShareIcon";
import {resolveOrganizerHomepageSections} from "../../../utilites/homepageSections.ts";
import {organizerHomepageLayout} from "../../../utilites/homepageSectionLayout.ts";
import {organizerSocialLinks, websiteHostname} from "../../../utilites/organizerLinks.ts";
import {CustomHomepageSection} from "../EventHomepage/Sections";
import {HomepageFrame} from "../HomepageFrame";
import {CoverMedia} from "../HomepageFrame/CoverMedia.tsx";
import {scrollBehavior} from "../../../utilites/motion.ts";
import {sizedImageUrl} from "../../../utilites/imageTransform.ts";
import {OrganizerPastEventsSection} from "./OrganizerPastEventsSection.tsx";

interface OrganizerHomepageProps {
    organizer?: Organizer;
    eventsData?: GenericPaginatedResponse<Event>
    isPastEvents?: boolean;
    isPreview?: boolean;
}

const KEEP_EVENTS_IN_VIEW = {keepEventsInView: true};

export const OrganizerHomepage = ({
                                      organizer,
                                      eventsData,
                                      isPastEvents = false,
                                      isPreview = false,
                                  }: OrganizerHomepageProps) => {
    const navigate = useNavigate();
    const location = useLocation();
    const [contactModalOpen, setContactModalOpen] = useState(false);
    const [descriptionExpanded, setDescriptionExpanded] = useState(false);
    const [descriptionOverflows, setDescriptionOverflows] = useState(false);
    const descriptionRef = useRef<HTMLDivElement>(null);
    const eventsSectionRef = useRef<HTMLElement>(null);

    useEffect(() => {
        const handle = setTimeout(() => {
            if (location.state?.keepEventsInView) {
                eventsSectionRef.current?.scrollIntoView({behavior: scrollBehavior(), block: 'start'});
                return;
            }
            window.scrollTo(0, 0);
        }, 100);

        return () => clearTimeout(handle);
    }, [location.pathname, location.search]);

    useEffect(() => {
        const node = descriptionRef.current;
        if (!node || typeof ResizeObserver === 'undefined') {
            return;
        }
        const measure = () => setDescriptionOverflows(node.scrollHeight > node.clientHeight + 1);
        const observer = new ResizeObserver(measure);
        observer.observe(node);
        measure();

        return () => observer.disconnect();
    }, [organizer?.description, descriptionExpanded]);

    useOrganizerTrackingPixels(
        organizer?.settings?.tracking_pixels
    );

    if (!organizer) {
        return null;
    }

    const handleFilterChange = (showPastEvents: boolean) => {
        navigate(
            showPastEvents ? `${organizerHomepagePath(organizer)}/past-events` : organizerHomepagePath(organizer),
            {state: KEEP_EVENTS_IN_VIEW},
        );
    };

    const themeSettings = validateThemeSettings(organizer.settings?.homepage_theme_settings);
    const socialLinks = organizerSocialLinks(organizer);
    const websiteUrl = organizer.website;
    const website = websiteUrl ? websiteHostname(websiteUrl) : null;
    const address = organizer.location?.structured_address;
    const shortAddress = getShortLocationDisplay(address);
    const logo = organizer.images?.find(image => image.type === 'ORGANIZER_LOGO');
    const cover = organizer.images?.find(image => image.type === 'ORGANIZER_COVER');
    const events = eventsData?.data || [];
    const layout = organizerHomepageLayout(resolveOrganizerHomepageSections(organizer.settings?.homepage_sections));
    const sectionClasses = {
        section: classes.section,
        sectionHeader: classes.sectionHeader,
        sectionTitle: classes.sectionTitle,
    };

    const eventCount = eventsData?.meta.total ?? 0;
    const eventCountLabel = eventCount === 0
        ? null
        : isPastEvents
            ? plural(eventCount, {one: '# past event', other: '# past events'})
            : plural(eventCount, {one: '# upcoming event', other: '# upcoming events'});

    const contactActions = (
        <div className={classes.contactActions}>
            <button type="button" onClick={() => setContactModalOpen(true)} className={classes.contactButton}>
                <IconMail/>
                {t`Contact`}
            </button>
            {socialLinks.map(({platform, url, label, Icon}) => (
                <a
                    key={platform}
                    href={url}
                    target="_blank"
                    rel="nofollow ugc noopener noreferrer"
                    title={label}
                    aria-label={label}
                    className={classes.iconButton}
                >
                    <Icon/>
                </a>
            ))}
        </div>
    );

    const renderEventsBlock = (heading: string | null | undefined, includesPastEvents: boolean) => {
        const showPastToggle = includesPastEvents || isPastEvents;
        const isFirstPage = (eventsData?.meta.current_page ?? 1) === 1;
        const spotlight = !isPastEvents && isFirstPage ? events[0] : undefined;
        const listEvents = spotlight ? events.slice(1) : events;

        return (
            <section key="events" className={classes.section} id="events" ref={eventsSectionRef} data-testid="organizer-events-section">
                <div className={classes.sectionHeader}>
                    <h2 className={classes.sectionTitle}>{heading || t`Events`}</h2>
                    {showPastToggle && (
                        <div className={classes.segmented}>
                            <button
                                type="button"
                                className={classes.segment}
                                data-active={!isPastEvents || undefined}
                                aria-pressed={!isPastEvents}
                                onClick={() => handleFilterChange(false)}
                            >
                                {t`Upcoming`}
                            </button>
                            <button
                                type="button"
                                className={classes.segment}
                                data-active={isPastEvents || undefined}
                                aria-pressed={isPastEvents}
                                onClick={() => handleFilterChange(true)}
                            >
                                {t`Past`}
                            </button>
                        </div>
                    )}
                </div>

                {events.length === 0 ? (
                    <div className={classes.emptyState}>
                        <span className={classes.emptyIcon}><IconCalendarOff/></span>
                        <p>{isPastEvents ? t`No past events` : t`No upcoming events`}</p>
                        {!isPastEvents && showPastToggle && (
                            <button type="button" className={classes.textButton} onClick={() => handleFilterChange(true)}>
                                {t`View past events`}
                            </button>
                        )}
                    </div>
                ) : (
                    <>
                        {spotlight && <EventSpotlight event={spotlight as Event}/>}
                        {listEvents.length > 0 && <EventList events={listEvents as Event[]} past={isPastEvents}/>}
                    </>
                )}

                {eventsData && eventsData.meta.total > eventsData.meta.per_page && (
                    <div className={classes.pagination}>
                        <Pagination
                            size="md"
                            radius="xl"
                            siblings={1}
                            marginTop={0}
                            total={eventsData.meta.last_page}
                            value={eventsData.meta.current_page}
                            onChange={(page) => {
                                const newPath = isPastEvents
                                    ? `${organizerHomepagePath(organizer)}/past-events?page=${page}`
                                    : `${organizerHomepagePath(organizer)}?page=${page}`;
                                navigate(newPath, {state: KEEP_EVENTS_IN_VIEW});
                            }}
                            className={classes.paginationControls}
                        />
                    </div>
                )}
            </section>
        );
    };

    const renderSection = (section: HomepageSection) => {
        switch (section.type) {
            case 'ABOUT':
                return organizer.description ? (
                    <section className={classes.section} id="about" data-testid="homepage-section-about">
                        <div className={classes.sectionHeader}>
                            <h2 className={classes.sectionTitle}>{section.heading || t`About`}</h2>
                        </div>
                        <UserGeneratedContent className={classes.description} html={organizer.description}/>
                    </section>
                ) : null;
            case 'CONTACT':
                return (
                    <section className={classes.section} id="contact" data-testid="homepage-section-contact">
                        <div className={classes.sectionHeader}>
                            <h2 className={classes.sectionTitle}>{section.heading || t`Contact`}</h2>
                        </div>
                        {contactActions}
                    </section>
                );
            case 'PAST_EVENTS':
                return (
                    <OrganizerPastEventsSection
                        organizer={organizer}
                        section={section}
                        sectionClasses={sectionClasses}
                        onViewAll={() => handleFilterChange(true)}
                    />
                );
            default:
                return <CustomHomepageSection images={organizer.images ?? []} section={section} sectionClasses={sectionClasses}/>;
        }
    };

    return (
        <>
            {!isPreview && organizer.status && organizer.id && (
                <StatusToggle
                    entityType="organizer"
                    entityId={organizer.id}
                    currentStatus={organizer.status as 'DRAFT' | 'LIVE'}
                    entityName={organizer.name}
                    onSuccess={() =>
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000)}
                />
            )}

            <OrganizerDocumentHead organizer={organizer}/>

            <HomepageFrame
                themeSettings={themeSettings}
                mirrorImageUrl={cover?.url}
                footerSource="app-organizer-footer"
                outside={(
                    <ContactOrganizerModal
                        opened={contactModalOpen}
                        onClose={() => setContactModalOpen(false)}
                        organizer={organizer}
                    />
                )}
            >
                {cover && <CoverMedia image={cover} alt={organizer.name} variant="banner"/>}

                <div className={classes.body}>
                    <header className={classes.profile}>
                        {logo
                            ? <img src={sizedImageUrl(logo.url, 240)} alt="" className={classes.avatar}/>
                            : <span className={classes.avatar} data-placeholder="" aria-hidden="true">{organizer.name.charAt(0).toUpperCase()}</span>}

                        <div className={classes.identity}>
                            <h1 className={classes.name}>{organizer.name}</h1>
                            {(shortAddress || website || eventCountLabel) && (
                                <div className={classes.meta}>
                                    {shortAddress && address && (
                                        <a href={getGoogleMapsUrl(address)} target="_blank" rel="nofollow ugc noopener noreferrer" className={classes.metaItem}>
                                            <IconMapPin/>
                                            {shortAddress}
                                        </a>
                                    )}
                                    {websiteUrl && website && (
                                        <a href={websiteUrl} target="_blank" rel="nofollow ugc noopener noreferrer" className={classes.metaItem}>
                                            <IconWorld/>
                                            {website}
                                        </a>
                                    )}
                                    {eventCountLabel && (
                                        <a href="#events" className={classes.metaItem}>
                                            <IconCalendarEvent/>
                                            {eventCountLabel}
                                        </a>
                                    )}
                                </div>
                            )}
                        </div>

                        {layout.heroAbout && organizer.description && (
                            <div className={classes.bio}>
                                {layout.heroAbout.heading && (
                                    <h2 className={classes.bioHeading}>{layout.heroAbout.heading}</h2>
                                )}
                                <div
                                    ref={descriptionRef}
                                    onFocus={() => setDescriptionExpanded(true)}
                                    className={classes.bioText}
                                    data-clamped={descriptionExpanded ? undefined : ''}
                                    data-overflows={descriptionOverflows || undefined}
                                >
                                    <UserGeneratedContent className={classes.description} html={organizer.description}/>
                                </div>
                                {(descriptionOverflows || descriptionExpanded) && (
                                    <button
                                        type="button"
                                        className={classes.textButton}
                                        aria-expanded={descriptionExpanded}
                                        onClick={() => setDescriptionExpanded(!descriptionExpanded)}
                                    >
                                        {descriptionExpanded ? t`Show less` : t`Read more`}
                                    </button>
                                )}
                            </div>
                        )}

                        <div className={classes.profileActions}>
                            {layout.heroContact && contactActions}
                            <ShareComponent title={organizer.name} text={organizer.name} url={organizerHomepageUrl(organizer)}>
                                <button type="button" className={classes.iconButton} title={t`Share`} aria-label={t`Share`}>
                                    <IconShare2/>
                                </button>
                            </ShareComponent>
                        </div>
                    </header>

                    {layout.blocks.map(block => block.kind === 'events'
                        ? renderEventsBlock(block.section.heading, block.includesPastEvents)
                        : <Fragment key={block.section.id}>{renderSection(block.section)}</Fragment>)}
                </div>
            </HomepageFrame>
        </>
    );
};

export default OrganizerHomepage;
