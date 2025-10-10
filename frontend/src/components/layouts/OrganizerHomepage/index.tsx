import {useLocation, useNavigate} from "react-router";
import {ActionIcon, Anchor, Button} from '@mantine/core';
import {EventCard} from './EventCard';
import classes from './OrganizerHomepage.module.scss';
import React, {useEffect, useState} from 'react';
import {Event, GenericPaginatedResponse, Organizer} from "../../../types.ts";
import {OrganizerDocumentHead} from "../../common/OrganizerDocumentHead";
import {IconExternalLink, IconMail, IconMapPin, IconWorld} from '@tabler/icons-react';
import {t} from "@lingui/macro";
import {PoweredByFooter} from "../../common/PoweredByFooter";
import {socialMediaConfig} from "../../../constants/socialMediaConfig";
import {ContactOrganizerModal} from "../../common/ContactOrganizerModal";
import {formatAddress, getShortLocationDisplay} from "../../../utilites/addressUtilities.ts";
import {organizerHomepagePath} from "../../../utilites/urlHelper.ts";
import {removeTransparency} from "../../../utilites/colorHelper.ts";
import {StatusToggle} from "../../common/StatusToggle";
import {getConfig} from "../../../utilites/config.ts";
import {Pagination} from "../../common/Pagination";

interface OrganizerHomepageProps {
    organizer?: Organizer;
    eventsData?: GenericPaginatedResponse<Event>
    isPastEvents?: boolean;
    isPreview?: boolean;
}

const ScrollToTop = () => {
    const {pathname} = useLocation();

    useEffect(() => {
        setTimeout(() => {
            window.scrollTo(0, 0);
        }, 100);
    }, [pathname]);

    return null;
}

export const OrganizerHomepage = ({
                                      organizer,
                                      eventsData,
                                      isPastEvents = false,
                                  }: OrganizerHomepageProps) => {
    const navigate = useNavigate();
    const [contactModalOpen, setContactModalOpen] = useState(false);

    if (!organizer) {
        return null;
    }

    const handleFilterChange = (showPastEvents: boolean) => {
        if (showPastEvents) {
            navigate(`${organizerHomepagePath(organizer)}/past-events`);
        } else {
            navigate(organizerHomepagePath(organizer));
        }
    };

    // Social links processing
    const socialLinks = organizer.settings?.social_media_handles ? Object.entries(organizer.settings.social_media_handles)
        .filter(([platform, handle]) => handle && socialMediaConfig[platform as keyof typeof socialMediaConfig])
        .map(([platform, handle]) => ({
            platform,
            handle: handle as string,
            config: socialMediaConfig[platform as keyof typeof socialMediaConfig]
        })) : [];

    const websiteUrl = organizer.website;

    const getGoogleMapsUrl = (locationDetails: any) => {
        if (!locationDetails) return '';
        return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(formatAddress(locationDetails))}`;
    };

    // Images
    const organizerLogo = organizer.images?.find(img => img.type === 'ORGANIZER_LOGO');
    const organizerCover = organizer.images?.find(img => img.type === 'ORGANIZER_COVER');

    const events = eventsData?.data || [];

    // Apply theme settings if available
    const themeSettings = organizer?.settings?.homepage_theme_settings;
    const backgroundType = themeSettings?.homepage_background_type || 'COLOR';
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
            <ScrollToTop/>
            {/* Status Toggle Banner */}
            {organizer?.status && organizer?.id && (
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

            {organizer && <OrganizerDocumentHead organizer={organizer}/>}
            <main className={classes.pageWrapper} style={themeStyles}>
                <style>
                    {`
                        body, .ssr-loader {
                            background-color: ${removeTransparency(themeSettings?.homepage_background_color!)} !important;
                        }
                    `}
                </style>
                {(organizerCover && backgroundType === 'MIRROR_COVER_IMAGE') && (
                    <div
                        className={classes.background}
                        style={{backgroundImage: `url(${organizerCover.url})`}}
                    />
                )}
                {(!organizerCover || backgroundType === 'COLOR') &&
                    <div className={classes.background}
                         style={{backgroundColor: 'var(--organizer-bg-color)'}}
                    />
                }
                <div className={classes.container}>
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
                                                                <a
                                                                    href={getGoogleMapsUrl(organizer.settings!.location_details)}
                                                                    target="_blank"
                                                                    rel="noopener noreferrer"
                                                                    className={classes.mapLink}
                                                                >
                                                                    <span>{getShortLocationDisplay(organizer.settings!.location_details)}</span>
                                                                    &nbsp;
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
                                <h2 className={classes.eventsTitle}>{isPastEvents ? t`Past Events` : t`Upcoming Events`}</h2>
                                <div className={classes.eventsControls}>
                                    <Button.Group>
                                        <Button
                                            variant={!isPastEvents ? 'filled' : 'default'}
                                            onClick={() => handleFilterChange(false)}
                                            size="xs"
                                            style={{
                                                backgroundColor: !isPastEvents ? themeSettings?.homepage_primary_color : 'transparent',
                                                color: !isPastEvents
                                                    ? themeSettings?.homepage_content_background_color
                                                    : themeSettings?.homepage_primary_color,
                                                borderColor: themeSettings?.homepage_primary_color,
                                            }}
                                        >
                                            {t`Upcoming`}
                                        </Button>
                                        <Button
                                            variant={isPastEvents ? 'filled' : 'default'}
                                            onClick={() => handleFilterChange(true)}
                                            size="xs"
                                            style={{
                                                backgroundColor: isPastEvents ? themeSettings?.homepage_primary_color : 'transparent',
                                                color: isPastEvents
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
                                        <p>{isPastEvents ? t`No past events` : t`No upcoming events`}</p>
                                    </div>
                                ) : (
                                    events.map((event) => (
                                        <EventCard
                                            key={event.id}
                                            event={event as Event}
                                            primaryColor={themeSettings?.homepage_primary_color}
                                        />
                                    ))
                                )}
                            </div>
                        </div>

                        {/* Pagination Section */}
                        {eventsData && eventsData.meta.total > eventsData.meta.per_page && (
                            <div className={classes.paginationSection}>
                                <div className={classes.paginationCard}>
                                    <Pagination
                                        size="md"
                                        siblings={1}
                                        marginTop={0}
                                        total={eventsData.meta.last_page}
                                        value={eventsData.meta.current_page}
                                        onChange={(page) => {
                                            const newPath = isPastEvents
                                                ? `${organizerHomepagePath(organizer)}/past-events?page=${page}`
                                                : `${organizerHomepagePath(organizer)}?page=${page}`;
                                            navigate(newPath);
                                        }}
                                        className={classes.paginationComponent}
                                        styles={{
                                            control: {
                                                backgroundColor: 'var(--content-bg-color)',
                                                border: '1px solid rgba(0, 0, 0, 0.1)',
                                                color: 'var(--primary-text-color)',
                                                fontSize: '0.875rem',
                                                fontWeight: 500,
                                                borderRadius: '8px',
                                                transition: 'all 0.2s ease',
                                                '&[data-active]': {
                                                    backgroundColor: themeSettings?.homepage_primary_color || 'var(--primary-color)',
                                                    borderColor: themeSettings?.homepage_primary_color || 'var(--primary-color)',
                                                    color: themeSettings?.homepage_content_background_color || 'white',
                                                },
                                                '&:hover': {
                                                    backgroundColor: 'rgba(0, 0, 0, 0.05)',
                                                    borderColor: 'rgba(0, 0, 0, 0.15)',
                                                },
                                            },
                                            dots: {
                                                color: 'var(--secondary-text-color)',
                                            },
                                        }}
                                    />
                                </div>
                            </div>
                        )}

                        {/* Footer Section */}
                        <div className={classes.footerSection}>
                            <div className={classes.footerContent}>
                                <div className={classes.footerLinks}>
                                    <Anchor
                                        href={getConfig('VITE_PRIVACY_URL', 'https://hi.events/privacy-policy?utm_source=app-organizer-footer')}
                                        className={classes.footerLink}
                                    >
                                        {t`Privacy Policy`}
                                    </Anchor>
                                    <span className={classes.footerSeparator}>•</span>
                                    <Anchor
                                        href={getConfig('VITE_TOS_URL', 'https://hi.events/terms-of-service?utm_source=app-organizer-footer')}
                                        className={classes.footerLink}
                                    >
                                        {t`Terms of Service`}
                                    </Anchor>
                                </div>
                                <PoweredByFooter
                                    className={classes.poweredByFooter}
                                />
                            </div>
                        </div>
                    </div>

                    {/* Contact Modal */}
                    <ContactOrganizerModal
                        opened={contactModalOpen}
                        onClose={() => setContactModalOpen(false)}
                        organizer={organizer}
                    />
                </div>
            </main>
        </>
    );
};

export default OrganizerHomepage;
