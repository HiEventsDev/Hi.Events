import classes from "./EventHomepage.module.scss";
import SelectProducts from "../../routes/product-widget/SelectProducts";
import "../../../styles/widget/default.scss";
import React, {useEffect, useRef, useState} from "react";
import {EventDocumentHead} from "../../common/EventDocumentHead";
import {eventCoverImage, eventHomepageUrl, imageUrl, organizerHomepageUrl} from "../../../utilites/urlHelper.ts";
import {Event, OrganizerStatus} from "../../../types.ts";
import {eventsClientPublic} from "../../../api/event.client.ts";
import {trackEvent, AnalyticsEvents} from "../../../utilites/analytics.ts";
import {EventNotAvailable} from "./EventNotAvailable";
import {
    IconArrowUpRight,
    IconCalendar,
    IconCalendarOff,
    IconCalendarPlus,
    IconExternalLink,
    IconMail,
    IconMapPin,
    IconMaximize,
    IconShare,
    IconTicket,
    IconWorld
} from "@tabler/icons-react";
import {Anchor} from "@mantine/core";
import {t} from "@lingui/macro";
import {PoweredByFooter} from "../../common/PoweredByFooter";
import {ContactOrganizerModal} from "../../common/ContactOrganizerModal";
import {socialMediaConfig} from "../../../constants/socialMediaConfig";
import {
    formatAddress,
    getGoogleMapsUrl,
    getShortLocationDisplay,
    isAddressSet
} from "../../../utilites/addressUtilities.ts";
import {StatusToggle} from "../../common/StatusToggle";
import {getConfig} from "../../../utilites/config.ts";
import {computeThemeVariables, validateThemeSettings} from "../../../utilites/themeUtils.ts";
import {removeTransparency} from "../../../utilites/colorHelper.ts";
import {ShareComponent} from "../../common/ShareIcon";
import {EventDateRange} from "../../common/EventDateRange";
import {CalendarOptionsPopover} from "../../common/CalendarOptionsPopover";
import {isDateInPast} from "../../../utilites/dates.ts";

interface EventHomepageProps {
    event?: Event;
    promoCodeValid?: boolean;
    promoCode?: string;
}

const EventHomepage = ({...loaderData}: EventHomepageProps) => {
    const {event, promoCodeValid, promoCode} = loaderData;
    const [showScrollButton, setShowScrollButton] = useState(false);
    const [contactModalOpen, setContactModalOpen] = useState(false);
    const ticketsSectionRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        let showTimer: NodeJS.Timeout;

        const checkTicketsPosition = () => {
            if (ticketsSectionRef.current) {
                const rect = ticketsSectionRef.current.getBoundingClientRect();
                const isBelowFold = rect.top > window.innerHeight;
                const isAboveView = rect.bottom < 0;
                const shouldShowButton = isBelowFold || isAboveView;
                setShowScrollButton(shouldShowButton);
            }
        };

        showTimer = setTimeout(() => {
            checkTicketsPosition();
        }, 500);

        const handleScroll = () => {
            checkTicketsPosition();
        };

        const handleResize = () => {
            checkTicketsPosition();
        };

        window.addEventListener('scroll', handleScroll);
        window.addEventListener('resize', handleResize);

        return () => {
            clearTimeout(showTimer);
            window.removeEventListener('scroll', handleScroll);
            window.removeEventListener('resize', handleResize);
        };
    }, []);

    const scrollToTickets = () => {
        ticketsSectionRef.current?.scrollIntoView({behavior: 'smooth', block: 'start'});
    };

    const handleExternalRegistrationClick = async () => {
        trackEvent(AnalyticsEvents.EXTERNAL_REGISTRATION_CLICKED);
        try {
            await eventsClientPublic.trackExternalRegistrationClick(event?.id);
        } catch (error) {
            // Silent fail - don't block navigation
            console.error('Failed to track click:', error);
        }
    };

    if (!event) {
        return <EventNotAvailable/>;
    }

    const rawThemeSettings = event?.settings?.homepage_theme_settings;
    const themeSettings = validateThemeSettings(rawThemeSettings);
    const cssVars = computeThemeVariables(themeSettings);
    const backgroundType = themeSettings.background_type;

    const themeStyles = {
        '--event-bg-color': themeSettings.background,
        '--event-content-bg-color': cssVars['--theme-surface'],
        '--event-primary-color': themeSettings.accent,
        '--event-primary-text-color': cssVars['--theme-text-primary'],
        '--event-secondary-color': cssVars['--theme-text-secondary'],
        '--event-secondary-text-color': cssVars['--theme-text-tertiary'],
        '--event-accent-contrast': cssVars['--theme-accent-contrast'],
        '--event-accent-soft': cssVars['--theme-accent-soft'],
        '--event-accent-muted': cssVars['--theme-accent-muted'],
        '--event-border-color': cssVars['--theme-border'],
    } as React.CSSProperties;

    const coverImageData = eventCoverImage(event);
    const coverImage = coverImageData?.url;
    const organizer = event.organizer!;
    const organizerSocials = organizer?.settings?.social_media_handles;
    const organizerLogo = imageUrl('ORGANIZER_LOGO', organizer?.images);
    const organizerLocation = organizer?.settings?.location_details;
    const websiteUrl = organizer?.website;
    const locationDetails = event.settings?.location_details;
    const isOnlineEvent = event.settings?.is_online_event;
    const hasLocation = isAddressSet(locationDetails) && !isOnlineEvent;

    const socialLinks = organizerSocials ? Object.entries(organizerSocials)
        .filter(([platform, handle]) => handle && socialMediaConfig[platform as keyof typeof socialMediaConfig])
        .map(([platform, handle]) => ({
            platform,
            handle: handle as string,
            config: socialMediaConfig[platform as keyof typeof socialMediaConfig]
        })) : [];

    const getStatusBadge = () => {
        const products = event.products || event.product_categories?.flatMap(c => c.products || []) || [];

        if (products.length === 0) {
            return null;
        }

        const availableProducts = products.filter(p => p.is_available && !p.is_sold_out);
        const allSoldOut = products.every(p => p.is_sold_out);

        if (allSoldOut) {
            return {text: t`Sold Out`, variant: 'danger'};
        }

        if (availableProducts.length === 0) {
            return null;
        }

        return {text: t`Tickets Available`, variant: 'success'};
    };

    const statusBadge = getStatusBadge();

    const mapUrl = event.settings?.maps_url || (locationDetails ? getGoogleMapsUrl(locationDetails) : null);

    return (
        <>
            {event?.status && event?.id && (
                <StatusToggle
                    entityType="event"
                    entityId={event.id}
                    currentStatus={event.status as 'DRAFT' | 'LIVE'}
                    entityName={event.title}
                    onSuccess={() =>
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000)}
                />
            )}

            <main
                className={classes.pageWrapper}
                style={themeStyles}
                data-mode={themeSettings.mode}
            >
                <style>
                    {`
                        body, .ssr-loader {
                            background-color: ${removeTransparency(themeSettings.background)} !important;
                        }
                    `}
                </style>

                {event && <EventDocumentHead event={event}/>}

                {/* Background */}
                {(coverImage && backgroundType === 'MIRROR_COVER_IMAGE') ? (
                    <div
                        className={classes.background}
                        style={{backgroundImage: `url(${coverImage})`}}
                    />
                ) : (
                    <div
                        className={classes.background}
                        style={{backgroundColor: 'var(--event-bg-color)'}}
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
                        {/* Main unified card */}
                        <div className={classes.mainCard}>
                            {/* Hero Section */}
                            <div className={classes.heroSection}>
                                {coverImage && (
                                    <div
                                        className={classes.coverWrapper}
                                        style={(coverImageData?.width && coverImageData?.height) ? {
                                            '--cover-aspect-ratio': `${coverImageData.width} / ${coverImageData.height}`,
                                        } as React.CSSProperties : undefined}
                                    >
                                        {coverImageData?.lqip_base64 && (
                                            <img
                                                src={coverImageData.lqip_base64}
                                                alt=""
                                                aria-hidden="true"
                                                className={classes.coverLqip}
                                            />
                                        )}
                                        <img
                                            src={coverImage}
                                            alt={event.title}
                                            className={classes.coverImage}
                                        />
                                        <div className={classes.heroGradient}/>
                                        {statusBadge && (
                                            <div className={classes.statusBadges}>
                                                <span className={classes.statusBadge}>
                                                    <IconTicket/>
                                                    {statusBadge.text}
                                                </span>
                                            </div>
                                        )}
                                    </div>
                                )}

                                {/* Event Header */}
                                <div className={classes.eventHeader}>
                                    <div className={classes.headerTopRow}>
                                        {organizer && organizer.status === OrganizerStatus.LIVE ? (
                                            <a
                                                href={organizerHomepageUrl(organizer)}
                                                className={classes.organizerPill}
                                            >
                                                {organizerLogo ? (
                                                    <img
                                                        src={organizerLogo}
                                                        alt={organizer.name}
                                                        className={classes.organizerPillAvatar}
                                                    />
                                                ) : (
                                                    <span className={classes.organizerPillAvatarPlaceholder}>
                                                        {organizer.name.charAt(0).toUpperCase()}
                                                    </span>
                                                )}
                                                <span className={classes.organizerPillName}>
                                                    {organizer.name}
                                                </span>
                                            </a>
                                        ) : (
                                            <div className={classes.organizerPill}>
                                                {organizerLogo ? (
                                                    <img
                                                        src={organizerLogo}
                                                        alt={organizer?.name || ''}
                                                        className={classes.organizerPillAvatar}
                                                    />
                                                ) : (
                                                    <span className={classes.organizerPillAvatarPlaceholder}>
                                                        {organizer?.name?.charAt(0).toUpperCase() || '?'}
                                                    </span>
                                                )}
                                                <span className={classes.organizerPillName}>
                                                    {organizer?.name}
                                                </span>
                                            </div>
                                        )}

                                        <div className={classes.actionButtons}>
                                            <ShareComponent
                                                title={'Check out this event: ' + event.title}
                                                text={'Check out this event: ' + event.title}
                                                url={eventHomepageUrl(event)}
                                                imageUrl={coverImage || undefined}
                                            >
                                                <button className={classes.actionButton} title={t`Share`}>
                                                    <IconShare/>
                                                </button>
                                            </ShareComponent>
                                            {/* Future enhancement: Favorite/Heart button */}
                                            {/* <button className={`${classes.actionButton} ${classes.favoriteButton}`} title={t`Save`}>
                                                <IconHeart />
                                            </button> */}
                                        </div>
                                    </div>

                                    <h1 className={classes.eventTitle}>{event.title}</h1>

                                    <div className={classes.eventMeta}>
                                        {/* Date/Time */}
                                        <div className={classes.metaItem}>
                                            <div className={classes.metaIconBox}>
                                                <IconCalendar/>
                                            </div>
                                            <div className={classes.metaContent}>
                                                <div className={classes.metaPrimary}>
                                                    <EventDateRange event={event}/>
                                                </div>
                                            </div>
                                            <CalendarOptionsPopover event={event}>
                                                <button className={classes.addToCalendarButton}>
                                                    <IconCalendarPlus/>
                                                    {t`Add to Calendar`}
                                                </button>
                                            </CalendarOptionsPopover>
                                        </div>

                                        {/* Event Ended */}
                                        {event.end_date && isDateInPast(event.end_date) && (
                                            <div className={classes.metaItem}>
                                                <div className={classes.metaIconBox}>
                                                    <IconCalendarOff/>
                                                </div>
                                                <div className={classes.metaContent}>
                                                    <div className={classes.metaPrimary}>{t`This event has ended`}</div>
                                                </div>
                                            </div>
                                        )}

                                        {/* Online Event */}
                                        {isOnlineEvent && (
                                            <div className={classes.metaItem}>
                                                <div className={classes.metaIconBox}>
                                                    <IconWorld/>
                                                </div>
                                                <div className={classes.metaContent}>
                                                    <div className={classes.metaPrimary}>{t`Online Event`}</div>
                                                    <div className={classes.metaSecondary}>
                                                        {t`Join from anywhere`}
                                                    </div>
                                                </div>
                                            </div>
                                        )}

                                        {/* Location */}
                                        {hasLocation && locationDetails && (
                                            <div className={classes.metaItem}>
                                                <div className={classes.metaIconBox}>
                                                    <IconMapPin/>
                                                </div>
                                                <div className={classes.metaContent}>
                                                    <div className={classes.metaPrimary}>
                                                        {locationDetails.venue_name}
                                                    </div>
                                                    <div className={classes.metaSecondary}>
                                                        {formatAddress(locationDetails)}
                                                    </div>
                                                    {mapUrl && (
                                                        <a
                                                            href={mapUrl}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            className={classes.metaLink}
                                                        >
                                                            {t`View on Google Maps`}
                                                            <IconExternalLink/>
                                                        </a>
                                                    )}
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </div>

                            {/* About Section */}
                            {event?.description && (
                                <div className={classes.section}>
                                    <div className={classes.sectionHeader}>
                                        <h2 className={classes.sectionTitle}>{t`About`}</h2>
                                    </div>
                                    <div
                                        className={classes.description}
                                        dangerouslySetInnerHTML={{__html: event.description}}
                                    />
                                </div>
                            )}

                            {/* Location Section (with map) */}
                            {hasLocation && locationDetails && (
                                <div className={classes.section}>
                                    <div className={classes.sectionHeader}>
                                        <h2 className={classes.sectionTitle}>{t`Location`}</h2>
                                    </div>
                                    <div className={classes.locationContent}>
                                        <div className={classes.venueDetails}>
                                            <div className={classes.venueName}>
                                                {locationDetails.venue_name}
                                            </div>
                                            <div className={classes.venueAddress}>
                                                {formatAddress(locationDetails)}
                                            </div>
                                            {mapUrl && (
                                                <a
                                                    href={mapUrl}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className={classes.directionsLink}
                                                >
                                                    <IconArrowUpRight/>
                                                    {t`Get Directions`}
                                                </a>
                                            )}
                                        </div>
                                        {mapUrl && (
                                            <a
                                                href={mapUrl}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className={classes.mapContainer}
                                            >
                                                <svg
                                                    viewBox="0 0 200 120"
                                                    preserveAspectRatio="xMidYMid slice"
                                                    style={{
                                                        width: '100%',
                                                        height: '100%',
                                                        position: 'absolute',
                                                        inset: 0,
                                                    }}
                                                >
                                                    <rect width="200" height="120" fill="var(--accent-soft)"/>
                                                    {/* River */}
                                                    <path d="M-5 95 Q30 85, 50 90 Q80 100, 110 88 Q140 75, 170 82 Q190 86, 205 80" stroke="var(--border-color)" strokeWidth="2" fill="none" opacity="0.3"/>
                                                    {/* Main roads */}
                                                    <line x1="0" y1="50" x2="200" y2="50" stroke="var(--border-color)" strokeWidth="2" opacity="0.2"/>
                                                    <line x1="100" y1="0" x2="100" y2="120" stroke="var(--border-color)" strokeWidth="2" opacity="0.2"/>
                                                    {/* Secondary roads */}
                                                    <line x1="0" y1="25" x2="200" y2="25" stroke="var(--border-color)" strokeWidth="1.5" opacity="0.2"/>
                                                    <line x1="0" y1="70" x2="85" y2="70" stroke="var(--border-color)" strokeWidth="1.5" opacity="0.2"/>
                                                    <line x1="115" y1="70" x2="200" y2="70" stroke="var(--border-color)" strokeWidth="1.5" opacity="0.2"/>
                                                    <line x1="50" y1="0" x2="50" y2="120" stroke="var(--border-color)" strokeWidth="1.5" opacity="0.2"/>
                                                    <line x1="150" y1="0" x2="150" y2="75" stroke="var(--border-color)" strokeWidth="1.5" opacity="0.2"/>
                                                    {/* Blocks/buildings */}
                                                    <rect x="110" y="28" width="14" height="10" fill="var(--border-color)" opacity="0.25" rx="1"/>
                                                    <rect x="20" y="55" width="12" height="10" fill="var(--border-color)" opacity="0.25" rx="1"/>
                                                </svg>
                                                <IconMapPin size={32} className={classes.mapPin}/>
                                                <div className={classes.mapOverlay}>
                                                    <span className={classes.mapOverlayLabel}>
                                                        <IconMaximize/>
                                                        {t`View Map`}
                                                    </span>
                                                </div>
                                            </a>
                                        )}
                                    </div>
                                </div>
                            )}

                            {/* Tickets Section */}
                            {!event.settings?.is_external_registration && (
                                <div className={`${classes.section} ${classes.ticketsSection}`} ref={ticketsSectionRef}
                                     id="tickets">
                                    <SelectProducts
                                        colors={{
                                            background: "transparent",
                                            primary: "var(--event-primary-color)",
                                            primaryText: "var(--event-primary-text-color)",
                                            secondary: "var(--event-primary-color)",
                                            secondaryText: "var(--event-accent-contrast)",
                                            bodyBackground: "var(--event-bg-color)",
                                        }}
                                        continueButtonText={event.settings?.continue_button_text}
                                        padding={"0px"}
                                        event={event}
                                        promoCodeValid={promoCodeValid}
                                        promoCode={promoCode}
                                        showPoweredBy={false}
                                    />
                                </div>
                            )}

                            {/* External Registration Section */}
                            {event.settings?.is_external_registration && (
                                <div className={`${classes.section} ${classes.ticketsSection}`} ref={ticketsSectionRef}
                                     id="tickets">
                                    <div className={classes.sectionHeader}>
                                        <h2 className={classes.sectionTitle}>{t`Registration`}</h2>
                                    </div>
                                    <div className={classes.externalRegistrationCard}>
                                        {event.settings.external_registration_host && (
                                            <p className={classes.externalRegistrationHost}>
                                                <strong>{t`Hosted by:`}</strong> {event.settings.external_registration_host}
                                            </p>
                                        )}
                                        <p className={classes.externalRegistrationMessage}>
                                            {event.settings.external_registration_message || t`This event uses external registration.`}
                                        </p>
                                        <a
                                            href={event.settings.external_registration_url}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className={classes.externalRegistrationButton}
                                            onClick={handleExternalRegistrationClick}
                                        >
                                            <IconExternalLink size={20}/>
                                            {event.settings.external_registration_button_text || t`Register Externally`}
                                        </a>
                                    </div>
                                </div>
                            )}

                            {/* Organizer Section */}
                            {organizer && organizer.status === OrganizerStatus.LIVE && (
                                <div className={classes.section} id="organizer">
                                    <div className={classes.sectionHeader}>
                                        <h2 className={classes.sectionTitle}>{t`Organizer`}</h2>
                                    </div>
                                    <div className={classes.organizerCard}>
                                        {organizerLogo ? (
                                            <img
                                                src={organizerLogo}
                                                alt={organizer.name}
                                                className={classes.organizerAvatar}
                                            />
                                        ) : (
                                            <div className={classes.organizerAvatarPlaceholder}>
                                                {organizer.name.charAt(0).toUpperCase()}
                                            </div>
                                        )}
                                        <div className={classes.organizerContent}>
                                            <div className={classes.organizerHeader}>
                                                <div>
                                                    <h3 className={classes.organizerName}>
                                                        <Anchor href={organizerHomepageUrl(organizer)}>
                                                            {organizer.name}
                                                        </Anchor>
                                                    </h3>
                                                    {getShortLocationDisplay(organizerLocation) && (
                                                        <div className={classes.organizerLocation}>
                                                            <IconMapPin/>
                                                            <a
                                                                href={getGoogleMapsUrl(organizerLocation!)}
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                            >
                                                                {getShortLocationDisplay(organizerLocation)}
                                                            </a>
                                                        </div>
                                                    )}
                                                </div>
                                            </div>

                                            {organizer.description && (
                                                <div
                                                    className={classes.organizerBio}
                                                    dangerouslySetInnerHTML={{__html: organizer.description}}
                                                />
                                            )}

                                            <div className={classes.organizerActions}>
                                                {socialLinks.length > 0 && (
                                                    <div className={classes.socialLinks}>
                                                        {socialLinks.map(({platform, handle, config}) => {
                                                            const IconComponent = config.icon;
                                                            const url = config.baseUrl + handle;
                                                            return (
                                                                <a
                                                                    key={platform}
                                                                    href={url}
                                                                    target="_blank"
                                                                    rel="noopener noreferrer"
                                                                    className={classes.socialLink}
                                                                    title={platform}
                                                                >
                                                                    <IconComponent size={18}/>
                                                                </a>
                                                            );
                                                        })}
                                                    </div>
                                                )}
                                                {websiteUrl && (() => {
                                                    try {
                                                        const hostname = new URL(websiteUrl).hostname;
                                                        return (
                                                            <a
                                                                href={websiteUrl}
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                className={classes.socialLink}
                                                                title={hostname}
                                                            >
                                                                <IconWorld size={18}/>
                                                            </a>
                                                        );
                                                    } catch {
                                                        return null;
                                                    }
                                                })()}
                                                <button
                                                    onClick={() => setContactModalOpen(true)}
                                                    className={classes.contactButton}
                                                >
                                                    <IconMail/>
                                                    {t`Contact`}
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* Footer */}
                        <div className={classes.footerSection}>
                            <div className={classes.footerLinks}>
                                <Anchor
                                    href={getConfig('VITE_PRIVACY_URL', 'https://hi.events/privacy-policy?utm_source=app-event-footer')}
                                    className={classes.footerLink}
                                >
                                    {t`Privacy Policy`}
                                </Anchor>
                                <Anchor
                                    href={getConfig('VITE_TOS_URL', 'https://hi.events/terms-of-service?utm_source=app-event-footer')}
                                    className={classes.footerLink}
                                >
                                    {t`Terms of Service`}
                                </Anchor>
                            </div>
                            <PoweredByFooter className={classes.poweredByFooter}/>
                        </div>
                    </div>

                    {/* Floating Scroll Button */}
                    {showScrollButton && !event.settings?.is_external_registration && (
                        <button
                            className={classes.scrollToTicketsButton}
                            onClick={scrollToTickets}
                        >
                            <IconTicket size={18}/>
                            {t`Get Tickets`}
                        </button>
                    )}

                    {/* Floating External Registration Button */}
                    {showScrollButton && event.settings?.is_external_registration && (
                        <a
                            href={event.settings.external_registration_url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className={classes.scrollToTicketsButton}
                            onClick={handleExternalRegistrationClick}
                        >
                            <IconExternalLink size={18}/>
                            {event.settings.external_registration_button_text || t`Register Externally`}
                        </a>
                    )}

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

export default EventHomepage;
