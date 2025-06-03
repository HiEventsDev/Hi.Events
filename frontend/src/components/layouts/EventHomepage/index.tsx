import "./styles.scss";
import {EventInformation} from "./EventInformation";
import classes from "./EventHomepage.module.scss";
import SelectProducts from "../../routes/product-widget/SelectProducts";
import "../../../styles/widget/default.scss";
import React from "react";
import {EventDocumentHead} from "../../common/EventDocumentHead";
import {eventCoverImageUrl, imageUrl, organizerHomepageUrl} from "../../../utilites/urlHelper.ts";
import {Event} from "../../../types.ts";
import {EventNotAvailable} from "./EventNotAvailable";
import {IconMapPin, IconWorld} from "@tabler/icons-react";
import {Anchor} from "@mantine/core";
import {t} from "@lingui/macro";
import {PoweredByFooter} from "../../common/PoweredByFooter";
import {socialMediaConfig} from "../../../constants/socialMediaConfig";
import {formatAddress} from "../../../utilites/formatAddress.tsx";

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
        <div style={styleOverrides} key={`${event.id}`} className={classes.pageWrapper}>
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
                {/* Cover Image Section - Standalone */}
                {coverImage && (
                    <div className={classes.coverSection}>
                        <div className={classes.coverWrapper}>
                            <img
                                alt={event?.title}
                                src={coverImage}
                                className={classes.coverImage}
                            />
                        </div>
                    </div>
                )}

                {/* Event Details Section */}
                <div className={classes.contentSection}>
                    <div className={classes.sectionContent}>
                        <EventInformation event={event} organizer={organizer}/>
                    </div>
                </div>

                {/* About Section */}
                {event?.description && (
                    <div className={classes.contentSection}>
                        <div className={classes.sectionContent}>
                            <h2 className={classes.sectionTitle}>{t`About this event`}</h2>
                            <div
                                className={classes.eventDescription}
                                dangerouslySetInnerHTML={{
                                    __html: event.description || '',
                                }}
                            />
                        </div>
                    </div>
                )}

                {/* Tickets Section */}
                <div className={classes.contentSection}>
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
                {organizer && (
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
                                        
                                        {organizerLocation?.city && (
                                            <div className={classes.organizerLocation}>
                                                <IconMapPin size={16} />
                                                <span>
                                                    {formatAddress(organizerLocation)}
                                                </span>
                                            </div>
                                        )}

                                        {websiteUrl && (
                                            <Anchor
                                                href={websiteUrl}
                                                target="_blank"
                                                className={classes.organizerWebsite}
                                            >
                                                {websiteUrl}
                                            </Anchor>
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
                                {(socialLinks.length > 0 || websiteUrl) && (
                                    <div className={classes.organizerSocials}>
                                        {websiteUrl && (
                                            <Anchor
                                                href={websiteUrl}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className={classes.socialLink}
                                            >
                                                <IconWorld size={24} />
                                            </Anchor>
                                        )}
                                        {socialLinks.map(({ platform, handle, config }) => {
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
                                                    <IconComponent size={24} />
                                                </Anchor>
                                            );
                                        })}
                                    </div>
                                )}
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
                                        href="https://hi.events/privacy-policy?utm_source=app-register-footer"
                                        className={classes.footerLink}
                                    >
                                        {t`Privacy Policy`}
                                    </Anchor>
                                    <span className={classes.footerSeparator}>•</span>
                                    <Anchor
                                        href="https://hi.events/terms-of-service?utm_source=app-register-footer"
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
            </div>
        </div>
    );
};

export default EventHomepage;
