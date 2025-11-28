import {EventInformation} from "./EventInformation";
import classes from "./EventHomepage.module.scss";
import SelectProducts from "../../routes/product-widget/SelectProducts";
import "../../../styles/widget/default.scss";
import React, {useEffect, useRef, useState} from "react";
import {EventDocumentHead} from "../../common/EventDocumentHead";
import {eventCoverImageUrl, imageUrl, organizerHomepageUrl} from "../../../utilites/urlHelper.ts";
import {Event, OrganizerStatus} from "../../../types.ts";
import {EventNotAvailable} from "./EventNotAvailable";
import {IconExternalLink, IconMail, IconMapPin, IconTicket, IconWorld} from "@tabler/icons-react";
import {Anchor} from "@mantine/core";
import {t} from "@lingui/macro";
import {PoweredByFooter} from "../../common/PoweredByFooter";
import {ContactOrganizerModal} from "../../common/ContactOrganizerModal";
import {socialMediaConfig} from "../../../constants/socialMediaConfig";
import {getGoogleMapsUrl, getShortLocationDisplay} from "../../../utilites/addressUtilities.ts";
import {StatusToggle} from "../../common/StatusToggle";
import {getConfig} from "../../../utilites/config.ts";
import {computeThemeVariables, validateThemeSettings} from "../../../utilites/themeUtils.ts";
import {removeTransparency} from "../../../utilites/colorHelper.ts";

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

    if (!event) {
        return <EventNotAvailable/>;
    }

    // Get theme settings
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

    const coverImage = eventCoverImageUrl(event);
    const organizer = event.organizer!;
    const organizerSocials = organizer?.settings?.social_media_handles;
    const organizerLogo = imageUrl('ORGANIZER_LOGO', organizer?.images);
    const organizerLocation = organizer?.settings?.location_details;
    const websiteUrl = organizer?.website;

    const socialLinks = organizerSocials ? Object.entries(organizerSocials)
        .filter(([platform, handle]) => handle && socialMediaConfig[platform as keyof typeof socialMediaConfig])
        .map(([platform, handle]) => ({
            platform,
            handle: handle as string,
            config: socialMediaConfig[platform as keyof typeof socialMediaConfig]
        })) : [];

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

            <main className={classes.pageWrapper} style={themeStyles}>
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

                <div className={classes.container}>
                    <div className={classes.wrapper}>
                        {/* Hero Section */}
                        <div className={classes.heroSection}>
                            {coverImage && (
                                <div className={classes.coverWrapper}>
                                    <img
                                        src={coverImage}
                                        alt={event.title}
                                        className={classes.coverImage}
                                    />
                                </div>
                            )}
                            <div className={classes.heroContent}>
                                <EventInformation event={event} organizer={organizer}/>
                            </div>
                        </div>

                        {/* About Section */}
                        {event?.description && (
                            <div className={classes.contentSection}>
                                <h2 className={classes.sectionTitle}>{t`About`}</h2>
                                <div
                                    className={classes.description}
                                    dangerouslySetInnerHTML={{__html: event.description}}
                                />
                            </div>
                        )}

                        {/* Tickets Section */}
                        <div className={classes.contentSection} ref={ticketsSectionRef}>
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

                        {/* Organizer Section */}
                        {organizer && organizer.status === OrganizerStatus.LIVE && (
                            <div className={classes.contentSection}>
                                <div className={classes.organizerInfo}>
                                    <div className={classes.organizerHeader}>
                                        {organizerLogo && (
                                            <img
                                                src={organizerLogo}
                                                alt={organizer.name}
                                                className={classes.organizerLogo}
                                            />
                                        )}
                                        <div className={classes.organizerDetails}>
                                            <h3 className={classes.organizerName}>
                                                <Anchor href={organizerHomepageUrl(organizer)}>
                                                    {organizer.name}
                                                </Anchor>
                                            </h3>

                                            {getShortLocationDisplay(organizerLocation) && (
                                                <div className={classes.metaItem}>
                                                    <IconMapPin size={15}/>
                                                    <a
                                                        href={getGoogleMapsUrl(organizerLocation!)}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                    >
                                                        <span>{getShortLocationDisplay(organizerLocation)}</span>
                                                        <IconExternalLink size={12}/>
                                                    </a>
                                                </div>
                                            )}

                                            {websiteUrl && (() => {
                                                try {
                                                    const hostname = new URL(websiteUrl).hostname;
                                                    return (
                                                        <div className={classes.metaItem}>
                                                            <IconWorld size={15}/>
                                                            <a
                                                                href={websiteUrl}
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                            >
                                                                {hostname}
                                                            </a>
                                                        </div>
                                                    );
                                                } catch {
                                                    return null;
                                                }
                                            })()}
                                        </div>
                                    </div>

                                    {organizer.description && (
                                        <div
                                            className={classes.organizerDescription}
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
                                                            className={classes.socialIcon}
                                                        >
                                                            <IconComponent size={18}/>
                                                        </a>
                                                    );
                                                })}
                                            </div>
                                        )}
                                        <button
                                            onClick={() => setContactModalOpen(true)}
                                            className={classes.contactButton}
                                        >
                                            <IconMail size={14}/>
                                            {t`Contact`}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Footer */}
                        <div className={classes.footerSection}>
                            <div className={classes.footerLinks}>
                                <Anchor
                                    href={getConfig('VITE_PRIVACY_URL', 'https://hi.events/privacy-policy?utm_source=app-event-footer')}
                                    className={classes.footerLink}
                                >
                                    {t`Privacy Policy`}
                                </Anchor>
                                <span className={classes.footerSeparator}>•</span>
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
                    {showScrollButton && (
                        <button
                            className={classes.scrollToTicketsButton}
                            onClick={scrollToTickets}
                        >
                            <IconTicket size={18}/>
                            {t`Get Tickets`}
                        </button>
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
