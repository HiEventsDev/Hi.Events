import {useLocation, useNavigate} from "react-router";
import {ActionIcon, Anchor} from '@mantine/core';
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
import {computeThemeVariables, validateThemeSettings} from "../../../utilites/themeUtils.ts";

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

    // Social links
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

    // Theme settings
    const rawThemeSettings = organizer?.settings?.homepage_theme_settings;
    const themeSettings = validateThemeSettings(rawThemeSettings);
    const cssVars = computeThemeVariables(themeSettings);
    const backgroundType = themeSettings.background_type;

    const themeStyles = {
        '--organizer-bg-color': themeSettings.background,
        '--organizer-content-bg-color': cssVars['--theme-surface'],
        '--organizer-primary-color': themeSettings.accent,
        '--organizer-primary-text-color': cssVars['--theme-text-primary'],
        '--organizer-secondary-color': cssVars['--theme-text-secondary'],
        '--organizer-secondary-text-color': cssVars['--theme-text-tertiary'],
        '--organizer-accent-contrast': cssVars['--theme-accent-contrast'],
        '--organizer-accent-soft': cssVars['--theme-accent-soft'],
        '--organizer-accent-muted': cssVars['--theme-accent-muted'],
        '--organizer-border-color': cssVars['--theme-border'],
    } as React.CSSProperties;

    return (
        <>
            <ScrollToTop/>
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
            <main className={classes.pageWrapper} style={themeStyles} data-mode={themeSettings.mode}>
                <style>
                    {`
                        body, .ssr-loader {
                            background-color: ${removeTransparency(themeSettings.background)} !important;
                        }
                    `}
                </style>

                {/* Background */}
                {(organizerCover && backgroundType === 'MIRROR_COVER_IMAGE') ? (
                    <div
                        className={classes.background}
                        style={{backgroundImage: `url(${organizerCover.url})`}}
                    />
                ) : (
                    <div
                        className={classes.background}
                        style={{backgroundColor: 'var(--organizer-bg-color)'}}
                    />
                )}
                <div
                    className={classes.backgroundOverlay}
                    style={backgroundType === 'MIRROR_COVER_IMAGE' ? {
                        '--overlay-color': themeSettings.background
                    } as React.CSSProperties : undefined}
                />

                <div className={classes.container}>
                    <div className={classes.wrapper}>
                        {/* Hero Section */}
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
                                                                <IconMapPin size={15} className={classes.metaIcon}/>
                                                                <a
                                                                    href={getGoogleMapsUrl(organizer.settings!.location_details)}
                                                                    target="_blank"
                                                                    rel="noopener noreferrer"
                                                                    className={classes.mapLink}
                                                                >
                                                                    <span>{getShortLocationDisplay(organizer.settings!.location_details)}</span>
                                                                    <IconExternalLink size={12}/>
                                                                </a>
                                                            </div>
                                                        )}
                                                        {websiteUrl && (
                                                            <div className={classes.metaItem}>
                                                                <IconWorld size={15} className={classes.metaIcon}/>
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
                                                                        variant="subtle"
                                                                        size="md"
                                                                    >
                                                                        <IconComponent size={16}/>
                                                                    </ActionIcon>
                                                                );
                                                            })}
                                                        </div>
                                                    )}
                                                    <button
                                                        onClick={() => setContactModalOpen(true)}
                                                        className={classes.contactButton}
                                                    >
                                                        <IconMail size={14} style={{marginRight: 6}}/>
                                                        {t`Contact`}
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    {organizer?.description && (
                                        <div
                                            className={classes.description}
                                            dangerouslySetInnerHTML={{__html: organizer.description}}
                                        />
                                    )}
                                </div>
                            </div>
                        </div>

                        {/* Events Section */}
                        <div className={classes.eventsSection}>
                            <div className={classes.eventsHeader}>
                                <h2 className={classes.eventsTitle}>
                                    {isPastEvents ? t`Past Events` : t`Upcoming Events`}
                                </h2>
                                <div className={classes.filterToggle}>
                                    <button
                                        className={`${classes.filterButton} ${!isPastEvents ? classes.filterButtonActive : ''}`}
                                        onClick={() => handleFilterChange(false)}
                                    >
                                        {t`Upcoming`}
                                    </button>
                                    <button
                                        className={`${classes.filterButton} ${isPastEvents ? classes.filterButtonActive : ''}`}
                                        onClick={() => handleFilterChange(true)}
                                    >
                                        {t`Past`}
                                    </button>
                                </div>
                            </div>

                            <div className={classes.eventsList}>
                                {events.length === 0 ? (
                                    <div className={classes.noEvents}>
                                        <p>{isPastEvents ? t`No past events` : t`No upcoming events`}</p>
                                    </div>
                                ) : (
                                    <div className={classes.eventsContainer}>
                                        {events.map((event) => (
                                            <EventCard
                                                key={event.id}
                                                event={event as Event}
                                                primaryColor={themeSettings.accent}
                                            />
                                        ))}
                                    </div>
                                )}
                            </div>

                            {eventsData && eventsData.meta.total > eventsData.meta.per_page && (
                                <div className={classes.paginationWrapper}>
                                    <Pagination
                                        size="sm"
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
                                    />
                                </div>
                            )}
                        </div>

                        {/* Footer */}
                        <div className={classes.footerSection}>
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
                            <PoweredByFooter className={classes.poweredByFooter}/>
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
