import React, { useEffect, useRef, useState } from "react";
import { EventDocumentHead } from "../../common/EventDocumentHead";
import { eventCoverImage, eventHomepageUrl, imageUrl, organizerHomepageUrl } from "../../../utilites/urlHelper.ts";
import { Event, OrganizerStatus } from "../../../types.ts";
import { EventNotAvailable } from "./EventNotAvailable";
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
import { Anchor } from "@mantine/core";
import { t } from "@lingui/macro";
import { PoweredByFooter } from "../../common/PoweredByFooter";
import { ContactOrganizerModal } from "../../common/ContactOrganizerModal";
import { socialMediaConfig } from "../../../constants/socialMediaConfig";
import {
    formatAddress,
    getGoogleMapsUrl,
    getShortLocationDisplay,
    isAddressSet
} from "../../../utilites/addressUtilities.ts";
import { StatusToggle } from "../../common/StatusToggle";
import { getConfig } from "../../../utilites/config.ts";
import { computeThemeVariables, validateThemeSettings } from "../../../utilites/themeUtils.ts";
import { removeTransparency, isColorDark } from "../../../utilites/colorHelper.ts";
import { ShareComponent } from "../../common/ShareIcon";
import { EventDateRange } from "../../common/EventDateRange";
import { CalendarOptionsPopover } from "../../common/CalendarOptionsPopover";
import { isDateInPast } from "../../../utilites/dates.ts";
import SelectProducts from "../../routes/product-widget/SelectProducts";
import "../../../styles/widget/default.scss";

interface EventHomepageProps {
    event?: Event;
    promoCodeValid?: boolean;
    promoCode?: string;
}

const EventHomepage = ({ ...loaderData }: EventHomepageProps) => {
    const { event, promoCodeValid, promoCode } = loaderData;
    const [showScrollButton, setShowScrollButton] = useState(false);
    const [contactModalOpen, setContactModalOpen] = useState(false);
    const ticketsSectionRef = useRef<HTMLDivElement>(null);

    // Keep scroll logic for mobile "Get Tickets" sticky button
    useEffect(() => {
        let showTimer: NodeJS.Timeout;
        const checkTicketsPosition = () => {
            if (ticketsSectionRef.current) {
                const rect = ticketsSectionRef.current.getBoundingClientRect();
                const isBelowFold = rect.top > window.innerHeight;
                const isAboveView = rect.bottom < 0;
                setShowScrollButton(isBelowFold || isAboveView);
            }
        };

        showTimer = setTimeout(() => { checkTicketsPosition(); }, 500);
        window.addEventListener('scroll', checkTicketsPosition);
        window.addEventListener('resize', checkTicketsPosition);
        return () => {
            clearTimeout(showTimer);
            window.removeEventListener('scroll', checkTicketsPosition);
            window.removeEventListener('resize', checkTicketsPosition);
        };
    }, []);

    const scrollToTickets = () => {
        ticketsSectionRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    if (!event) { return <EventNotAvailable />; }

    // --- Dynamic Theming Logic ---
    const rawThemeSettings = event?.settings?.homepage_theme_settings || event?.organizer?.settings?.homepage_theme_settings;
    const themeSettings = validateThemeSettings(rawThemeSettings);

    const mode = themeSettings.mode || 'light';
    const isMirror = themeSettings.background_type === 'MIRROR_COVER_IMAGE';

    // Fallback to light theme defaults
    const fallbackBg = mode === 'dark' ? '#0a0a0a' : '#f4f5f6';
    const backgroundColor = themeSettings.background || fallbackBg;
    const accentColor = themeSettings.accent || '#40296C';

    const isBgDark = isColorDark(backgroundColor);
    const isAccentDark = isColorDark(accentColor);

    // Text colors for elements directly on the background
    const bgTextPrimary = isBgDark ? 'text-white' : 'text-gray-900';
    const bgTextSecondary = isBgDark ? 'text-gray-400' : 'text-gray-500';
    const bgProseClasses = isBgDark
        ? 'prose-invert text-gray-300 prose-headings:text-white prose-a:text-white hover:prose-a:text-gray-200'
        : 'text-gray-600 prose-headings:text-gray-900 prose-a:text-gray-900 hover:prose-a:text-gray-700';

    // Card styles and colors
    let cardBg = '';
    let ticketCardBg = '';
    let isCardDark = false;

    if (isMirror) {
        if (mode === 'dark') {
            cardBg = 'bg-black/60 backdrop-blur-2xl';
            ticketCardBg = 'bg-black/70 backdrop-blur-3xl';
            isCardDark = true;
        } else {
            cardBg = 'bg-white/80 backdrop-blur-2xl';
            ticketCardBg = 'bg-white/95 backdrop-blur-3xl';
            isCardDark = false;
        }
    } else {
        if (mode === 'dark') {
            cardBg = 'bg-white/5 backdrop-blur-xl';
            ticketCardBg = 'bg-white/10 backdrop-blur-2xl';
            isCardDark = isBgDark; // Transparent cards rely on bg
        } else {
            cardBg = 'bg-white';
            ticketCardBg = 'bg-white';
            isCardDark = false; // Solid white card
        }
    }

    const cardTextPrimary = isCardDark ? 'text-white' : 'text-gray-900';
    const cardTextSecondary = isCardDark ? 'text-gray-400' : 'text-gray-500';
    const cardProseClasses = isCardDark
        ? 'prose-invert text-gray-300 prose-headings:text-white prose-a:text-white hover:prose-a:text-gray-200'
        : 'text-gray-600 prose-headings:text-gray-900 prose-a:text-gray-900 hover:prose-a:text-gray-700';

    const textPrimary = cardTextPrimary;
    const textSecondary = cardTextSecondary;
    const proseClasses = cardProseClasses;

    // Link specific text overrides to beat Mantine's global anchor styles
    const linkTextPrimary = textPrimary.replace('text-', '!text-');
    const linkTextSecondary = textSecondary.replace('text-', '!text-');
    const bgLinkTextPrimary = bgTextPrimary.replace('text-', '!text-');
    const bgLinkTextSecondary = bgTextSecondary.replace('text-', '!text-');

    const borderStyle = isCardDark ? 'border-white/10' : 'border-gray-200/80';
    const cardHover = isCardDark ? 'hover:bg-white/10' : 'hover:bg-gray-50';
    const iconWrapperBg = isCardDark ? 'bg-white/10 text-white' : 'bg-gray-100 text-gray-700';
    const subtleBtnBorder = isCardDark ? 'border-white/10' : 'border-gray-200';
    const subtleBtnBg = isCardDark ? 'bg-white/10 hover:bg-white/20' : 'bg-white hover:bg-gray-50';
    // -----------------------------

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
        if (products.length === 0) return null;
        const availableProducts = products.filter(p => p.is_available && !p.is_sold_out);
        const allSoldOut = products.every(p => p.is_sold_out);
        if (allSoldOut) return { text: t`Sold Out`, variant: 'danger' };
        if (availableProducts.length === 0) return null;
        return { text: t`Tickets Available`, variant: 'success' };
    };
    const statusBadge = getStatusBadge();
    const mapUrl = event.settings?.maps_url || (locationDetails ? getGoogleMapsUrl(locationDetails) : null);

    return (
        <div
            className={`min-h-screen font-sans relative overflow-x-hidden ${isBgDark ? 'selection:bg-white/20' : 'selection:bg-black/10'}`}
            style={{ backgroundColor }}
        >
            {event?.status && event?.id && (
                <StatusToggle
                    entityType="event"
                    entityId={event.id}
                    currentStatus={event.status as 'DRAFT' | 'LIVE'}
                    entityName={event.title}
                    onSuccess={() => setTimeout(() => { window.location.reload(); }, 1000)}
                />
            )}

            <style>
                {`
                    body, .ssr-loader { background-color: ${backgroundColor} !important; }
                    /* Form elements in dark/light mode */
                    .hi-widget-container { background: transparent !important; color: ${cardTextPrimary.replace('text-', '')} !important; }
                    .hi-widget-product { 
                        background: ${isCardDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.02)'} !important; 
                        border: 1px solid ${isCardDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.05)'} !important; 
                        border-radius: 0.75rem !important; 
                    }
                    .hi-widget-product:hover { 
                        background: ${isCardDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.04)'} !important; 
                        border-color: ${isCardDark ? 'rgba(255,255,255,0.2)' : 'rgba(0,0,0,0.1)'} !important; 
                    }
                `}
            </style>

            {event && <EventDocumentHead event={event} />}

            {/* Ambient Background Glow */}
            {coverImage && isMirror && (
                <div className="absolute top-0 left-0 right-0 h-[600px] w-full overflow-hidden pointer-events-none z-0">
                    <div
                        className="absolute inset-0 bg-no-repeat bg-cover bg-center origin-top transform scale-125 opacity-30 blur-[100px]"
                        style={{ backgroundImage: `url(${coverImage})` }}
                    />
                    <div
                        className="absolute inset-0"
                        style={{ background: `linear-gradient(to bottom, transparent, ${backgroundColor}80, ${backgroundColor})` }}
                    />
                </div>
            )}

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-10 pb-24 relative z-10 flex flex-col lg:flex-row gap-8 lg:gap-12 lg:items-start mt-8">

                {/* Left Column - Fixed on Desktop */}
                <div className="lg:w-1/3 flex flex-col gap-6 lg:sticky lg:top-8">
                    {/* Cover Image Card */}
                    {coverImage && (
                        <div className={`relative aspect-square w-full rounded-[2rem] overflow-hidden shadow-xl border ${borderStyle} group ${isCardDark ? 'bg-gray-900' : 'bg-gray-100'}`}>
                            {coverImageData?.lqip_base64 && (
                                <img src={coverImageData.lqip_base64} alt="" aria-hidden="true" className="absolute inset-0 w-full h-full object-cover blur-md" />
                            )}
                            <img src={coverImage} alt={event.title} className="absolute inset-0 w-full h-full object-cover transition-transform duration-700 ease-in-out group-hover:scale-105" />
                            <div className="absolute inset-0 bg-gradient-to-t from-black/40 via-black/5 to-transparent" />

                            {/* Status Badge Over Image */}
                            {statusBadge && (
                                <div className="absolute top-4 left-4">
                                    <div className={`px-3 py-1.5 text-xs font-bold uppercase tracking-wider rounded-full flex items-center gap-1.5 backdrop-blur-md shadow-lg ${statusBadge.variant === 'danger' ? 'bg-red-500/90 text-white border border-red-400/30' : 'bg-black/40 text-white border border-white/20'}`}>
                                        <IconTicket size={14} />
                                        {statusBadge.text}
                                    </div>
                                </div>
                            )}

                            <div className="absolute top-4 right-4 flex gap-2">
                                <ShareComponent title={'Check out this event: ' + event.title} text={'Check out this event: ' + event.title} url={eventHomepageUrl(event)} imageUrl={coverImage || undefined}>
                                    <button className="h-10 w-10 flex items-center justify-center rounded-full bg-black/40 backdrop-blur-md border border-white/20 text-white hover:bg-black/60 transition shadow-lg" title={t`Share`}>
                                        <IconShare size={18} />
                                    </button>
                                </ShareComponent>
                            </div>
                        </div>
                    )
                    }

                    {/* Organizer Card Desktop */}
                    {
                        organizer && organizer.status === OrganizerStatus.LIVE && (
                            <div className={`${cardBg} border ${borderStyle} rounded-3xl p-6 hidden lg:flex flex-col gap-4 shadow-sm`}>
                                <h3 className={`text-sm font-semibold tracking-wider uppercase ${textSecondary}`}>{t`Presented By`}</h3>
                                <div className="flex items-center gap-3">
                                    {organizerLogo ? (
                                        <img src={organizerLogo} alt={organizer.name} className={`w-12 h-12 rounded-full border ${borderStyle} ${isCardDark ? 'bg-gray-800' : 'bg-white'} object-cover`} />
                                    ) : (
                                        <div className={`w-12 h-12 rounded-full border ${borderStyle} ${isCardDark ? 'bg-gray-800 text-gray-300' : 'bg-gray-100 text-gray-600'} flex items-center justify-center text-xl font-bold`}>
                                            {organizer.name.charAt(0).toUpperCase()}
                                        </div>
                                    )}
                                    <div>
                                        <Anchor href={organizerHomepageUrl(organizer)} className={`${linkTextPrimary} font-semibold text-lg hover:underline decoration-2 underline-offset-4 decoration-current/30`}>
                                            {organizer.name}
                                        </Anchor>
                                        {getShortLocationDisplay(organizerLocation) && (
                                            <div className={`${textSecondary} text-sm flex items-center gap-1 mt-0.5`}>
                                                <IconMapPin size={14} className={textSecondary} />
                                                <a href={getGoogleMapsUrl(organizerLocation!)} target="_blank" rel="noopener noreferrer" className={`hover:${linkTextPrimary} ${linkTextSecondary}`}>
                                                    {getShortLocationDisplay(organizerLocation)}
                                                </a>
                                            </div>
                                        )}
                                    </div>
                                </div>

                                {/* Organizer Actions */}
                                <div className="flex flex-wrap gap-2 mt-2">
                                    <button onClick={() => setContactModalOpen(true)} className={`flex-1 py-2 px-4 rounded-xl text-sm font-medium transition flex justify-center items-center gap-2 border shadow-sm ${subtleBtnBg} ${subtleBtnBorder} ${linkTextPrimary}`}>
                                        <IconMail size={16} /> {t`Contact`}
                                    </button>
                                    {websiteUrl && (
                                        <a href={websiteUrl} target="_blank" rel="noopener noreferrer" className={`h-[38px] w-[38px] flex items-center justify-center rounded-xl transition border shadow-sm ${subtleBtnBg} ${subtleBtnBorder} ${linkTextPrimary}`}>
                                            <IconWorld size={18} />
                                        </a>
                                    )}
                                    {socialLinks.map(({ platform, handle, config }) => {
                                        const IconComponent = config.icon;
                                        return (
                                            <a key={platform} href={config.baseUrl + handle} target="_blank" rel="noopener noreferrer" className={`h-[38px] w-[38px] flex items-center justify-center rounded-xl transition border shadow-sm ${subtleBtnBg} ${subtleBtnBorder} ${linkTextPrimary}`} title={platform}>
                                                <IconComponent size={18} />
                                            </a>
                                        );
                                    })}
                                </div>
                            </div>
                        )}
                </div>

                {/* Right Column - Main Content */}
                <div className="lg:w-2/3 flex flex-col gap-10">

                    {/* Header Details */}
                    <div className="flex flex-col gap-6">
                        <h1 className={`text-4xl sm:text-5xl lg:text-5xl xl:text-6xl font-black tracking-tight leading-[1.1] ${bgTextPrimary}`}>
                            {event.title}
                        </h1>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            {/* Date Card */}
                            <div className={`${cardBg} border ${borderStyle} rounded-[1.5rem] p-5 flex items-start gap-4 transition-colors shadow-sm ${cardHover}`}>
                                <div className={`p-3 rounded-2xl ${iconWrapperBg}`}>
                                    {event.end_date && isDateInPast(event.end_date) ? <IconCalendarOff size={24} /> : <IconCalendar size={24} />}
                                </div>
                                <div>
                                    <div className={`font-medium text-base mb-1 ${cardTextPrimary}`}>
                                        {event.end_date && isDateInPast(event.end_date) ? t`This event has ended` : <EventDateRange event={event} />}
                                    </div>
                                    <CalendarOptionsPopover event={event}>
                                        <button className={`text-sm font-medium ${linkTextSecondary} hover:${linkTextPrimary} flex items-center gap-1.5 transition`}>
                                            <IconCalendarPlus size={16} /> {t`Add to Calendar`}
                                        </button>
                                    </CalendarOptionsPopover>
                                </div>
                            </div>

                            {/* Location Card */}
                            {hasLocation && locationDetails && (
                                <div className={`${cardBg} border ${borderStyle} rounded-[1.5rem] p-5 flex items-start gap-4 transition-colors shadow-sm ${cardHover}`}>
                                    <div className={`p-3 rounded-2xl ${iconWrapperBg}`}>
                                        <IconMapPin size={24} />
                                    </div>
                                    <div>
                                        <div className={`font-medium text-base ${cardTextPrimary}`}>{locationDetails.venue_name}</div>
                                        <div className={`${cardTextSecondary} text-sm mt-0.5 line-clamp-2`}>{formatAddress(locationDetails)}</div>
                                        {mapUrl && (
                                            <a href={mapUrl} target="_blank" rel="noopener noreferrer" className={`text-sm font-medium mt-1 inline-flex items-center gap-1 ${linkTextSecondary} hover:${linkTextPrimary} transition`}>
                                                {t`View Map`} <IconExternalLink size={14} />
                                            </a>
                                        )}
                                    </div>
                                </div>
                            )}

                            {isOnlineEvent && (
                                <div className={`${cardBg} border ${borderStyle} rounded-[1.5rem] p-5 flex items-start gap-4 transition-colors shadow-sm ${cardHover}`}>
                                    <div className={`p-3 rounded-2xl ${iconWrapperBg}`}>
                                        <IconWorld size={24} />
                                    </div>
                                    <div>
                                        <div className={`font-medium text-base ${cardTextPrimary}`}>{t`Online Event`}</div>
                                        <div className={`${cardTextSecondary} text-sm mt-0.5`}>{t`Join from anywhere`}</div>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Registration / Tickets Section */}
                    <div className={`${ticketCardBg} border ${borderStyle} rounded-[2rem] overflow-hidden shadow-xl relative`} ref={ticketsSectionRef} id="tickets">
                        {/* A very subtle top highlight line */}
                        <div className="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-transparent via-current to-transparent opacity-10" style={{ color: accentColor }}></div>

                        <div className="p-6 sm:p-8">
                            <h2 className={`text-2xl font-bold mb-6 flex items-center gap-3 ${textPrimary}`}>
                                <IconTicket className={textSecondary} /> {t`Registration`}
                            </h2>
                            <div className="w-full">
                                <SelectProducts
                                    colors={{
                                        background: "transparent",
                                        primary: accentColor,
                                        primaryText: isCardDark ? "#ffffff" : "#111827",
                                        secondary: accentColor,
                                        secondaryText: isAccentDark ? "#ffffff" : "#111827",
                                        bodyBackground: "transparent",
                                    }}
                                    continueButtonText={event.settings?.continue_button_text}
                                    padding={"0px"}
                                    event={event}
                                    promoCodeValid={promoCodeValid}
                                    promoCode={promoCode}
                                    showPoweredBy={false}
                                />
                            </div>
                        </div>
                    </div>

                    {/* About Section */}
                    {event?.description && (
                        <div className="mt-4">
                            <h2 className={`text-2xl font-bold mb-6 pl-1 tracking-tight ${textPrimary}`}>{t`About`}</h2>
                            <div
                                className={`prose prose-lg max-w-none prose-p:leading-relaxed ${proseClasses}`}
                                dangerouslySetInnerHTML={{ __html: event.description }}
                            />
                        </div>
                    )}

                    {/* Location Details block below description */}
                    {hasLocation && locationDetails && (
                        <div className={`mt-6 border-t pt-8 pl-1 ${borderStyle}`}>
                            <h2 className={`text-2xl font-bold mb-6 tracking-tight ${textPrimary}`}>{t`Location`}</h2>
                            <div className="flex flex-col sm:flex-row gap-6">
                                <div className="flex-1 shrink-0">
                                    <h3 className={`text-lg font-semibold mb-1 ${bgTextPrimary}`}>{locationDetails.venue_name}</h3>
                                    <p className={`whitespace-pre-line leading-relaxed mb-4 ${bgTextSecondary}`}>{formatAddress(locationDetails)}</p>
                                    {mapUrl && (
                                        <a href={mapUrl} target="_blank" rel="noopener noreferrer" className={`inline-flex items-center gap-2 px-4 py-2.5 rounded-xl font-medium transition-colors border shadow-sm ${subtleBtnBg} ${subtleBtnBorder} ${linkTextPrimary}`}>
                                            {t`View on Google Maps`} <IconExternalLink size={16} />
                                        </a>
                                    )}
                                </div>
                                {mapUrl && (
                                    <a href={mapUrl} target="_blank" rel="noopener noreferrer" className={`w-full sm:w-64 h-48 rounded-2xl overflow-hidden relative group block shrink-0 border ${borderStyle} ${isCardDark ? 'bg-white/5' : 'bg-gray-100'} mx-auto sm:mx-0`}>
                                        <div className={`absolute inset-0 opacity-30 ${isCardDark ? "bg-[url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyMCIgaGVpZ2h0PSIyMCI+CjxyZWN0IHdpZHRoPSIyMCIgaGVpZ2h0PSIyMCIgZmlsbD0ibm9uZSI+PC9yZWN0Pgo8Y2lyY2xlIGN4PSIyIiBjeT0iMiIgcj0iMSIgZmlsbD0icmdiYSgyNTUsMjU1LDI1NSwwLjI1KSI+PC9jaXJjbGU+Cjwvc3ZnPg==')]" : "bg-[url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyMCIgaGVpZ2h0PSIyMCI+CjxyZWN0IHdpZHRoPSIyMCIgaGVpZ2h0PSIyMCIgZmlsbD0ibm9uZSI+PC9yZWN0Pgo8Y2lyY2xlIGN4PSIyIiBjeT0iMiIgcj0iMSIgZmlsbD0icmdiYSgwLDAsMCwwLjE1KSI+PC9jaXJjbGU+Cjwvc3ZnPg==')]"}`}></div>
                                        <div className="absolute inset-0 flex items-center justify-center bg-black/20 backdrop-blur-[2px] opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                            <span className="flex items-center gap-2 text-white font-medium bg-black/60 px-4 py-2 rounded-full backdrop-blur-md">
                                                <IconMaximize size={18} /> {t`View Map`}
                                            </span>
                                        </div>
                                        <IconMapPin size={36} className="absolute inset-0 m-auto text-black/50 drop-shadow-[0_0_15px_rgba(255,255,255,0.5)] mix-blend-overlay" />
                                    </a>
                                )}
                            </div>
                        </div>
                    )}

                    {/* Organizer Card (Mobile View) */}
                    {organizer && organizer.status === OrganizerStatus.LIVE && (
                        <div className={`mt-8 pt-8 border-t lg:hidden pl-1 ${borderStyle}`}>
                            <h2 className={`text-2xl font-bold mb-6 tracking-tight ${textPrimary}`}>{t`Presented By`}</h2>
                            <div className={`flex border p-6 rounded-3xl flex-col gap-4 shadow-sm ${cardBg} ${borderStyle}`}>
                                <div className="flex items-center gap-4">
                                    {organizerLogo ? (
                                        <img src={organizerLogo} alt={organizer.name} className={`w-14 h-14 rounded-full border ${borderStyle} ${isCardDark ? 'bg-gray-800' : 'bg-white'} object-cover`} />
                                    ) : (
                                        <div className={`w-14 h-14 rounded-full border ${borderStyle} ${isCardDark ? 'bg-gray-800 text-gray-300' : 'bg-gray-100 text-gray-600'} flex items-center justify-center text-xl font-bold`}>
                                            {organizer.name.charAt(0).toUpperCase()}
                                        </div>
                                    )}
                                    <div>
                                        <Anchor href={organizerHomepageUrl(organizer)} className={`font-bold text-xl hover:underline decoration-2 underline-offset-4 decoration-current/30 ${linkTextPrimary}`}>
                                            {organizer.name}
                                        </Anchor>
                                        {getShortLocationDisplay(organizerLocation) && (
                                            <div className={`${textSecondary} text-sm flex items-center gap-1 mt-1`}>
                                                <IconMapPin size={16} className={textSecondary} />
                                                <a href={getGoogleMapsUrl(organizerLocation!)} target="_blank" rel="noopener noreferrer" className={`hover:${linkTextPrimary} ${linkTextSecondary}`}>
                                                    {getShortLocationDisplay(organizerLocation)}
                                                </a>
                                            </div>
                                        )}
                                    </div>
                                </div>

                                {organizer.description && (
                                    <div className={`prose prose-sm line-clamp-3 mt-2 ${proseClasses}`} dangerouslySetInnerHTML={{ __html: organizer.description }} />
                                )}

                                <div className={`flex flex-wrap gap-2 mt-2 pt-4 border-t ${borderStyle}`}>
                                    <button onClick={() => setContactModalOpen(true)} className={`flex-1 py-2 px-4 rounded-xl text-sm font-medium transition flex items-center justify-center gap-2 border shadow-sm ${subtleBtnBg} ${subtleBtnBorder} ${linkTextPrimary}`}>
                                        <IconMail size={16} /> {t`Contact`}
                                    </button>
                                    {socialLinks.map(({ platform, handle, config }) => {
                                        const IconComponent = config.icon;
                                        return (
                                            <a key={platform} href={config.baseUrl + handle} target="_blank" rel="noopener noreferrer" className={`h-[42px] w-[42px] flex items-center justify-center rounded-xl transition border shadow-sm ${subtleBtnBg} ${subtleBtnBorder} ${linkTextPrimary}`} title={platform}>
                                                <IconComponent size={20} />
                                            </a>
                                        );
                                    })}
                                </div>
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Footer */}
            <div className={`max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 border-t flex flex-col sm:flex-row items-center justify-between gap-6 relative z-10 opacity-70 hover:opacity-100 transition-opacity ${borderStyle}`}>
                <div className={`flex items-center gap-6 text-sm ${bgTextSecondary}`}>
                    <a href={getConfig('VITE_PRIVACY_URL', 'https://hi.events/privacy-policy')} className={`hover:${bgLinkTextPrimary} transition text-inherit !text-current`}>
                        {t`Privacy Policy`}
                    </a>
                    <a href={getConfig('VITE_TOS_URL', 'https://hi.events/terms-of-service')} className={`hover:${bgLinkTextPrimary} transition text-inherit !text-current`}>
                        {t`Terms of Service`}
                    </a>
                </div>
                <PoweredByFooter className={`${bgTextSecondary} hover:${bgLinkTextPrimary} transition`} />
            </div>

            {/* Floating Registration Button (Mobile) */}
            {showScrollButton && (
                <div
                    className="fixed bottom-0 left-0 right-0 p-4 z-50 lg:hidden flex justify-center pb-safe pt-8 pointer-events-none"
                    style={{ background: `linear-gradient(to top, ${backgroundColor}f2, ${backgroundColor}e6 50%, transparent)` }}
                >
                    <button
                        onClick={scrollToTickets}
                        style={{ backgroundColor: accentColor, color: '#fff' }}
                        className="pointer-events-auto px-8 py-3.5 rounded-full font-bold shadow-[0_8px_30px_rgb(0,0,0,0.2)] flex items-center justify-center gap-2 w-full max-w-sm transition-all active:scale-95 border border-black/10 hover:brightness-110"
                    >
                        <IconTicket size={20} /> {t`Get Tickets`}
                    </button>
                </div>
            )}

            <ContactOrganizerModal opened={contactModalOpen} onClose={() => setContactModalOpen(false)} organizer={organizer} />
        </div>
    );
};

export default EventHomepage;
