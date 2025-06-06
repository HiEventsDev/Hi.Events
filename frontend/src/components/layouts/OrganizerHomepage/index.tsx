import {useParams} from "react-router";
import {ActionIcon, Button} from '@mantine/core';
import {EventCard} from './EventCard';
import classes from './OrganizerHomepage.module.scss';
import React, {useMemo, useState} from 'react';
import {Event, GenericDataResponse, Organizer, QueryFilterOperator} from "../../../types.ts";
import {useGetOrganizerPublicEvents} from "../../../queries/useGetOrganizerEventsPublic.ts";
import {OrganizerDocumentHead} from "../../common/OrganizerDocumentHead";
import {IconArrowRight, IconExternalLink, IconMail, IconMapPin, IconWorld} from '@tabler/icons-react';
import {t} from "@lingui/macro";
import {PoweredByFooter} from "../../common/PoweredByFooter";
import {socialMediaConfig} from "../../../constants/socialMediaConfig";
import {ContactOrganizerModal} from "../../common/ContactOrganizerModal";
import {formatAddress, getShortLocationDisplay} from "../../../utilites/addressUtilities.ts";

interface OrganizerHomepageProps {
    organizer?: Organizer;
    upcomingEventsData?: GenericDataResponse<Event>;
    isPreview?: boolean;
}

export const OrganizerHomepage = ({
                                      organizer,
                                      upcomingEventsData: upcomingEventsFromLoader,
                                      isPreview
                                  }: OrganizerHomepageProps) => {
    const {organizerId} = useParams();

    const [eventFilter, setEventFilter] = useState<'upcoming' | 'past'>('upcoming');
    const [upcomingPage, setUpcomingPage] = useState(1);
    const [pastPage, setPastPage] = useState(1);
    const [contactModalOpen, setContactModalOpen] = useState(false);

    const currentDate = useMemo(() => new Date().toISOString(), []);

    // Only fetch past events when selected
    const pastQueryFilters = useMemo(() => ({
        pageNumber: pastPage,
        perPage: 25,
        sortBy: 'start_date',
        sortDirection: 'desc' as const, // Most recent past events first
        filterFields: {
            start_date: {
                operator: QueryFilterOperator.LessThan,
                value: currentDate
            }
        }
    }), [pastPage, currentDate]);

    // Only fetch more upcoming events when paginating
    const upcomingQueryFilters = useMemo(() => ({
        pageNumber: upcomingPage,
        perPage: 25,
        sortBy: 'start_date',
        sortDirection: 'asc' as const,
        filterFields: {
            start_date: {
                operator: QueryFilterOperator.GreaterThanOrEquals,
                value: currentDate
            }
        }
    }), [upcomingPage, currentDate]);

    // For preview mode or initial load, don't fetch
    const skipUpcomingQuery = isPreview || upcomingPage === 1;

    // Fetch upcoming events (skip on first page since we have SSR data)
    const {
        data: upcomingEventsData,
        isLoading: isLoadingUpcoming
    } = useGetOrganizerPublicEvents(organizerId!, upcomingQueryFilters, {
        enabled: !skipUpcomingQuery && !!organizerId && eventFilter === 'upcoming',
    });

    // Fetch past events (only when selected)
    const {
        data: pastEventsData,
        isLoading: isLoadingPast
    } = useGetOrganizerPublicEvents(organizerId!, pastQueryFilters, {
        enabled: !!organizerId && eventFilter === 'past' && !isPreview,
    });


    if (!organizer) {
        return null;
    }

    const handleFilterChange = (filter: 'upcoming' | 'past') => {
        setEventFilter(filter);
    };

    const handleNextPage = () => {
        if (eventFilter === 'upcoming') {
            setUpcomingPage(prev => prev + 1);
        } else {
            setPastPage(prev => prev + 1);
        }
    };

    // Get the appropriate events data based on filter
    let eventsData: any;
    if (eventFilter === 'upcoming') {
        // Use SSR data for first page, fetched data for subsequent pages
        eventsData = upcomingPage === 1 ? upcomingEventsFromLoader : upcomingEventsData;
    } else {
        eventsData = pastEventsData;
    }

    const isLoading = eventFilter === 'upcoming' ? isLoadingUpcoming : isLoadingPast;

    // Social links processing
    const socialLinks = organizer.settings?.social_media_handles ? Object.entries(organizer.settings.social_media_handles)
        .filter(([platform, handle]) => handle && socialMediaConfig[platform as keyof typeof socialMediaConfig])
        .map(([platform, handle]) => ({
            platform,
            handle: handle as string,
            config: socialMediaConfig[platform as keyof typeof socialMediaConfig]
        })) : [];

    const websiteUrl = organizer.website;

    // Create Google Maps URL using full address like in OrderSummaryAndProducts
    const getGoogleMapsUrl = (locationDetails: any) => {
        if (!locationDetails) return '';
        return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(formatAddress(locationDetails))}`;
    };

    // Images
    const organizerLogo = organizer.images?.find(img => img.type === 'ORGANIZER_LOGO');
    const organizerCover = organizer.images?.find(img => img.type === 'ORGANIZER_COVER');

    // Loading state
    if (isLoading && (
        (eventFilter === 'upcoming' && upcomingPage > 1) ||
        (eventFilter === 'past' && pastPage === 1)
    )) {
        return (
            <main className={classes.container}>
                <div className={classes.wrapper}>
                    <div className={classes.loadingMessage}>
                        {t`Loading events...`}
                    </div>
                </div>
            </main>
        );
    }

    const events = eventsData?.data || eventsData || [];

    const hasMorePages = eventsData?.meta &&
        (eventFilter === 'upcoming' ? upcomingPage : pastPage) < eventsData.meta.last_page;

    // Apply theme settings if available
    const themeSettings = organizer?.settings?.homepage_theme_settings;
    const themeStyles = themeSettings ? {
        '--organizer-bg-color': themeSettings.homepage_background_color || '#f5f5f5',
        '--organizer-content-bg-color': themeSettings.homepage_content_background_color || '#ffffff',
        '--organizer-primary-color': themeSettings.homepage_primary_color || '#8b5cf6',
        '--organizer-primary-text-color': themeSettings.homepage_primary_text_color || '#1a1a1a',
        '--organizer-secondary-color': themeSettings.homepage_secondary_color || '#6366f1',
        '--organizer-secondary-text-color': themeSettings.homepage_secondary_text_color || '#6b7280',
    } as React.CSSProperties : {};

    return (
        <>
            {organizer && <OrganizerDocumentHead organizer={organizer}/>}
            <main className={classes.container} style={themeStyles}>
                <style>
                    {`
                        body, .ssr-loader {
                            background-color: ${themeSettings?.homepage_background_color || '#f5f5f5'} !important;
                        }
                    `}
                </style>
                <div className={classes.wrapper}>
                    {/* Combined Cover and Organizer Info Section */}
                    <div className={classes.heroSection}>
                        {organizerCover && (
                            <div className={classes.coverWrapper}>
                                <img
                                    src={organizerCover.url}
                                    alt="Cover"
                                    className={classes.coverImage}
                                />
                                <div className={classes.coverOverlay}/>
                            </div>
                        )}
                        <div className={classes.organizerContentWrapper}>
                            <div className={classes.organizerContent}>
                                {/* Sophisticated Left-Aligned Layout */}
                                <div className={classes.organizerProfile}>
                                    <div className={classes.profileMain}>
                                        {organizerLogo && (
                                            <div className={classes.logoWrapper}>
                                                <img
                                                    src={organizerLogo.url}
                                                    alt="Logo"
                                                    className={classes.logo}
                                                />
                                            </div>
                                        )}
                                        <div className={classes.organizerInfo}>
                                            <div className={classes.nameSection}>
                                                <h1>{organizer?.name}</h1>
                                                <div className={classes.organizerMeta}>
                                                    {getShortLocationDisplay(organizer?.settings?.location_details) && (
                                                        <div className={classes.metaItem}>
                                                            <IconMapPin size={16} className={classes.metaIcon}/>
                                                            <span>{getShortLocationDisplay(organizer.settings!.location_details)}</span>
                                                            <a
                                                                href={getGoogleMapsUrl(organizer.settings!.location_details)}
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                className={classes.mapLink}
                                                            >
                                                                <IconExternalLink size={14}/>
                                                            </a>
                                                        </div>
                                                    )}
                                                    {websiteUrl && (
                                                        <div className={classes.metaItem}>
                                                            <IconWorld size={16} className={classes.metaIcon}/>
                                                            <a
                                                                href={websiteUrl}
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                            >
                                                                {new URL(websiteUrl).hostname}
                                                            </a>
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
                                            {/* Actions moved here for better flow */}
                                            <div className={classes.profileActions}>
                                                {(socialLinks.length > 0) && (
                                                    <div className={classes.socialLinks}>
                                                        {socialLinks.map(({platform, handle, config}) => {
                                                            const IconComponent = config.icon;
                                                            const url = config.baseUrl + handle;
                                                            return (
                                                                <ActionIcon
                                                                    key={platform}
                                                                    component="a"
                                                                    href={url}
                                                                    target="_blank"
                                                                    rel="noopener noreferrer"
                                                                    className={classes.socialIcon}
                                                                    size="lg"
                                                                >
                                                                    <IconComponent size={18}/>
                                                                </ActionIcon>
                                                            );
                                                        })}
                                                    </div>
                                                )}
                                                <Button
                                                    leftSection={<IconMail size={16}/>}
                                                    onClick={() => setContactModalOpen(true)}
                                                    className={classes.contactButton}
                                                    variant="outline"
                                                    size="sm"
                                                >
                                                    {t`Contact`}
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                {/* Description flows naturally below */}
                                {organizer?.description && (
                                    <div className={classes.description}
                                         dangerouslySetInnerHTML={{__html: organizer.description}}/>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Events Header Card - Compact Section */}
                    <div className={classes.eventsHeaderSection}>
                        <div className={classes.eventsHeaderCard}>
                            <h2 className={classes.eventsTitle}>{eventFilter === 'upcoming' ? t`Upcoming Events` : t`Past Events`}</h2>
                            <div className={classes.eventsControls}>
                                <Button.Group>
                                    <Button
                                        variant={eventFilter === 'upcoming' ? 'filled' : 'default'}
                                        onClick={() => handleFilterChange('upcoming')}
                                        size="sm"
                                        style={{
                                            backgroundColor: eventFilter === 'upcoming' ? themeSettings?.homepage_primary_color : 'transparent',
                                            color: eventFilter === 'upcoming'
                                                ? themeSettings?.homepage_content_background_color
                                                : themeSettings?.homepage_primary_color,
                                            borderColor: themeSettings?.homepage_primary_color,
                                        }}
                                    >
                                        {t`Upcoming`}
                                    </Button>
                                    <Button
                                        variant={eventFilter === 'past' ? 'filled' : 'default'}
                                        onClick={() => handleFilterChange('past')}
                                        size="sm"
                                        style={{
                                            backgroundColor: eventFilter === 'past' ? themeSettings?.homepage_primary_color : 'transparent',
                                            color: eventFilter === 'past'
                                                ? themeSettings?.homepage_content_background_color
                                                : themeSettings?.homepage_primary_color,
                                            borderColor: themeSettings?.homepage_primary_color,
                                        }}
                                    >
                                        {t`Past`}
                                    </Button>
                                </Button.Group>
                            </div>
                        </div>
                    </div>

                    {/* Individual Event Cards - Floating */}
                    <div className={classes.eventsListSection}>
                        <div className={classes.eventsContainer}>
                            {events.length === 0 ? (
                                <div className={classes.noEvents}>
                                    <p>{eventFilter === 'upcoming' ? t`No upcoming events` : t`No past events`}</p>
                                </div>
                            ) : (
                                events.map((event) => (
                                    <EventCard
                                        key={event.id}
                                        event={event as Event}
                                        primaryColor={themeSettings?.homepage_primary_color || '#8b5cf6'}
                                    />
                                ))
                            )}
                        </div>

                        {hasMorePages && (
                            <div className={classes.loadMoreContainer}>
                                <Button
                                    onClick={handleNextPage}
                                    rightSection={<IconArrowRight size={16}/>}
                                    size="lg"
                                    className={classes.loadMoreButton}
                                    style={{
                                        background: themeSettings?.homepage_primary_color || 'var(--primary-color)',
                                    }}
                                >
                                    {t`Show More Events`}
                                </Button>
                            </div>
                        )}
                    </div>

                    {/* Footer Section */}
                    <div className={classes.footerSection}>
                        <PoweredByFooter className={classes.poweredBy}/>
                    </div>
                </div>

                {/* Contact Modal */}
                <ContactOrganizerModal
                    opened={contactModalOpen}
                    onClose={() => setContactModalOpen(false)}
                    organizer={organizer}
                />
            </main>
        </>
    );
};

export default OrganizerHomepage;
