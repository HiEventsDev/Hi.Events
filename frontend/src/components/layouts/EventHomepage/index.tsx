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
import {Anchor, Button} from "@mantine/core";
import {t} from "@lingui/macro";
import {PoweredByFooter} from "../../common/PoweredByFooter";
import {ContactOrganizerModal} from "../../common/ContactOrganizerModal";
import {socialMediaConfig} from "../../../constants/socialMediaConfig";
import {getGoogleMapsUrl, getShortLocationDisplay} from "../../../utilites/addressUtilities.ts";
import {StatusToggle} from "../../common/StatusToggle";
import {getConfig} from "../../../utilites/config.ts";

interface EventHomepageProps {
    colors?: {
        bodyBackground?: string;
        background?: string;
        primary?: string;
        primaryText?: string;
        secondary?: string;
        secondaryText?: string;
    };
    backgroundType?: 'COLOR' | 'MIRROR_COVER_IMAGE',
    continueButtonText?: string;
    event?: Event;
    promoCodeValid?: boolean;
    promoCode?: string;
}

const EventHomepage = ({colors, continueButtonText, backgroundType, ...loaderData}: EventHomepageProps) => {
    const {event, promoCodeValid, promoCode} = loaderData;
    const [showScrollButton, setShowScrollButton] = useState(false);
    const [contactModalOpen, setContactModalOpen] = useState(false);
    const ticketsSectionRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        let showTimer: NodeJS.Timeout;

        const checkTicketsPosition = () => {
            if (ticketsSectionRef.current) {
                const rect = ticketsSectionRef.current.getBoundingClientRect();
                // Check if tickets section is below the fold or out of view
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

    const styleOverrides = {
        "--homepage-body-background-color":
            colors?.bodyBackground || event?.settings?.homepage_body_background_color,
        "--homepage-background-color":
            colors?.background || event?.settings?.homepage_background_color,
        "--homepage-primary-color":
            colors?.primary || event?.settings?.homepage_primary_color,
        "--homepage-primary-text-color":
            colors?.primaryText || event?.settings?.homepage_primary_text_color,
        "--homepage-secondary-color":
            colors?.secondary || event?.settings?.homepage_secondary_color,
        "--homepage-secondary-text-color":
            colors?.secondaryText || event?.settings?.homepage_secondary_text_color,
    } as React.CSSProperties;

    if (!event) {
        return <EventNotAvailable/>;
    }

    const coverImage = eventCoverImageUrl(event);
    const organizer = event.organizer!;
    const organizerSocials = organizer?.settings?.social_media_handles;
    const organizerLogo = imageUrl('ORGANIZER_LOGO', organizer?.images);
    const organizerLocation = organizer?.settings?.location_details;
    const websiteUrl = organizer?.website;

    // Process social links
    const socialLinks = organizerSocials ? Object.entries(organizerSocials)
        .filter(([platform, handle]) => handle && socialMediaConfig[platform as keyof typeof socialMediaConfig])
        .map(([platform, handle]) => ({
            platform,
            handle: handle as string,
            config: socialMediaConfig[platform as keyof typeof socialMediaConfig]
        })) : [];

    return (
        <>
            {/* Status Toggle Banner */}
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

            <div style={styleOverrides} key={`${event.id}`} className={classes.pageWrapper}>
                <style>
                    {`
                        body, .ssr-loader {
                            background-color: ${colors?.bodyBackground || event?.settings?.homepage_body_background_color || '#f5f5f5'} !important;
                        }
                    `}
                </style>
                {event && <EventDocumentHead event={event}/>}
                {(coverImage && backgroundType === 'MIRROR_COVER_IMAGE') && (
                    <div
                        className={classes.background}
                        style={{backgroundImage: `url(${coverImage})`}}
                    />
                )}
                {(!coverImage || backgroundType === 'COLOR') &&
                    <div className={classes.background}
                         style={{backgroundColor: 'var(--homepage-body-background-color)'}}
                    />
                }
                <div id={"event-homepage"} className={classes.mainContainer}>
                    {/* Hero Section - Combined Cover and Event Details */}
                    <div className={classes.contentSection}>
                        {coverImage && (
                            <div className={classes.coverWrapper}>
                                <img
                                    alt={event?.title}
                                    src={coverImage}
                                    className={classes.coverImage}
                                />
                            </div>
                        )}
                        <div className={classes.sectionContent}>
                            <EventInformation event={event} organizer={organizer}/>
                        </div>
                    </div>

                    {/* About Section - Separate */}
                    {event?.description && (
                        <div className={classes.contentSection}>
                            <div className={classes.sectionContent}>
                                <h2 className={classes.sectionTitle}>{t`About`}</h2>
                                <div
                                    className={classes.eventDescription}
                                    dangerouslySetInnerHTML={{
                                        __html: event.description || '',
                                    }}
                                />
                            </div>
                        </div>
                    )}

                    {/* Tickets Section - Separate */}
                    <div className={classes.contentSection} ref={ticketsSectionRef}>
                        <div className={classes.sectionContent}>
                            <SelectProducts
                                colors={{
                                    background: "transparent",
                                    primary: "var(--homepage-primary-color)",
                                    primaryText: "var(--homepage-primary-text-color)",
                                    secondary: "var(--homepage-secondary-color)",
                                    secondaryText: "var(--homepage-secondary-text-color)",
                                    bodyBackground: "var(--homepage-body-background-color)",
                                }}
                                continueButtonText={continueButtonText}
                                padding={"0px"}
                                event={event}
                                promoCodeValid={promoCodeValid}
                                promoCode={promoCode}
                                showPoweredBy={false}
                            />
                        </div>
                    </div>

                    {/* Organizer Section */}
                    {organizer && organizer.status === OrganizerStatus.LIVE && (
                        <div className={classes.contentSection}>
                            <div className={classes.sectionContent}>
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
                                                <Anchor
                                                    href={organizerHomepageUrl(organizer)}
                                                >
                                                    {organizer.name}
                                                </Anchor>
                                            </h3>

                                            {getShortLocationDisplay(organizerLocation) && (
                                                <div className={classes.organizerLocation}>
                                                    <IconMapPin size={16}/>
                                                    <Anchor
                                                        href={getGoogleMapsUrl(organizerLocation!)}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className={classes.mapLink}
                                                    >
                                                        <span>{getShortLocationDisplay(organizerLocation)}</span>
                                                        &nbsp;
                                                        <IconExternalLink size={14}/>
                                                    </Anchor>
                                                </div>
                                            )}

                                            {websiteUrl && (
                                                <div className={classes.organizerWebsite}>
                                                    <IconWorld size={16}/>
                                                    <Anchor
                                                        href={websiteUrl}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                    >
                                                        {new URL(websiteUrl).hostname}
                                                    </Anchor>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                    {organizer.description && (
                                        <div
                                            className={classes.organizerDescription}
                                            dangerouslySetInnerHTML={{
                                                __html: organizer.description
                                            }}
                                        />
                                    )}
                                    <div className={classes.organizerSocials}>
                                        {socialLinks.map(({platform, handle, config}) => {
                                            const IconComponent = config.icon;
                                            const url = config.baseUrl + handle;
                                            return (
                                                <Anchor
                                                    key={platform}
                                                    href={url}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className={classes.socialLink}
                                                >
                                                    <IconComponent size={24}/>
                                                </Anchor>
                                            );
                                        })}
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
                    )}

                    {/* Footer Section */}
                    <div className={classes.contentSection}>
                        <div className={classes.sectionContent}>
                            <footer className={classes.footerSection}>
                                <div className={classes.footerContent}>
                                    <div className={classes.footerLinks}>
                                        <Anchor
                                            href={getConfig('VITE_TOS_URL', 'https://hi.events/terms-of-service?utm_source=event=homepage-footer') as string}
                                            className={classes.footerLink}
                                        >
                                            {t`Privacy Policy`}
                                        </Anchor>
                                        <span className={classes.footerSeparator}>•</span>
                                        <Anchor
                                            href={getConfig('VITE_PRIVACY_URL', 'https://hi.events/privacy-policy?utm_source=event=homepage-footer') as string}
                                            className={classes.footerLink}
                                        >
                                            {t`Terms of Service`}
                                        </Anchor>
                                    </div>
                                    <PoweredByFooter
                                        className={classes.poweredByFooter}
                                    />
                                </div>
                            </footer>
                        </div>
                    </div>

                    {/* Floating Scroll to Tickets Button */}
                    {showScrollButton && (
                        <Button
                            className={classes.scrollToTicketsButton}
                            onClick={scrollToTickets}
                            leftSection={<IconTicket size={20}/>}
                            size="md"
                            radius="xl"
                            style={{
                                background: 'var(--homepage-background-color)',
                                color: 'var(--homepage-primary-text-color)',
                            }}
                        >
                            {t`Scroll to Tickets`}
                        </Button>
                    )}
                </div>

                {/* Contact Modal */}
                <ContactOrganizerModal
                    opened={contactModalOpen}
                    onClose={() => setContactModalOpen(false)}
                    organizer={organizer}
                />
            </div>
        </>
    );
};

export default EventHomepage;
