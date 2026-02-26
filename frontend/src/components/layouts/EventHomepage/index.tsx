import React, { useEffect, useMemo, useRef, useState } from "react";
import { EventDocumentHead } from "../../common/EventDocumentHead";
import { eventCoverImage, eventHomepageUrl, imageUrl, organizerHomepageUrl } from "../../../utilites/urlHelper.ts";
import { Event, OrganizerStatus } from "../../../types.ts";
import { EventNotAvailable } from "./EventNotAvailable";
import {
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
import { computeThemeVariables, validateThemeSettings, getContrastColor, generateMeshColors, detectMode } from "../../../utilites/themeUtils.ts";
// eslint-disable-next-line @typescript-eslint/no-unused-vars
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
    const [contactModalOpen, setContactModalOpen] = useState(false);
    const ticketsSectionRef = useRef<HTMLDivElement>(null);

    if (!event) { return <EventNotAvailable />; }

    // --- Dynamic Theming Logic ---
    const rawThemeSettings = event?.settings?.homepage_theme_settings || event?.organizer?.settings?.homepage_theme_settings;
    const themeSettings = validateThemeSettings(rawThemeSettings);

    const backgroundType = themeSettings.background_type || 'color';
    const backgroundColor = themeSettings.background || (themeSettings.mode === 'dark' ? '#050505' : '#f9fafb');

    const mode = themeSettings.mode === 'auto' ? detectMode(backgroundColor) : (themeSettings.mode || 'light');
    const isCardDark = mode === 'dark';

    const accentColor = themeSettings.accent || '#40296C';

    // eslint-disable-next-line react-hooks/exhaustive-deps
    const meshColors = useMemo(() => generateMeshColors(backgroundColor), [backgroundColor]);

    const isBgDark = backgroundType === 'MIRROR_COVER_IMAGE' || backgroundType === 'IMAGE'
        ? isCardDark
        : detectMode(backgroundColor) === 'dark';

    // Text colors for elements directly on the background
    const bgTextPrimary = isBgDark ? '!text-white' : '!text-gray-900';
    const bgTextSecondary = isBgDark ? '!text-white/70' : '!text-gray-600';

    // Heavy glassmorphism UI 
    const cardBg = isCardDark ? 'bg-black/20 backdrop-blur-2xl shadow-xl' : 'bg-white/40 backdrop-blur-2xl shadow-xl';
    const ticketCardBg = isCardDark ? 'bg-black/20 backdrop-blur-2xl shadow-xl' : 'bg-white/40 backdrop-blur-2xl shadow-xl';

    const cardTextPrimary = isCardDark ? 'text-white' : 'text-gray-900';
    const cardTextSecondary = isCardDark ? 'text-white/70' : 'text-gray-500';
    const cardProseClasses = isCardDark
        // eslint-disable-next-line lingui/no-unlocalized-strings
        ? 'prose-invert text-white/80 prose-headings:text-white prose-headings:font-bold prose-a:text-[var(--prose-accent)] hover:prose-a:brightness-110'
        // eslint-disable-next-line lingui/no-unlocalized-strings
        : 'text-gray-600 prose-headings:text-gray-900 prose-headings:font-bold prose-a:text-[var(--prose-accent)] hover:prose-a:brightness-90';

    const textPrimary = cardTextPrimary;
    const textSecondary = cardTextSecondary;
    const proseClasses = cardProseClasses;

    // Link specific text overrides to beat Mantine's global anchor styles
    const linkTextPrimary = isCardDark ? '!text-white' : '!text-gray-900';
    const linkTextSecondary = isCardDark ? '!text-white/70' : '!text-gray-500';
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const bgLinkTextPrimary = isBgDark ? '!text-white' : '!text-gray-900';
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const bgLinkTextSecondary = isBgDark ? '!text-white/70' : '!text-gray-600';

    const borderStyle = isCardDark ? 'border-white/10' : 'border-white/50';
    const cardHover = isCardDark ? 'hover:bg-black/30' : 'hover:bg-white/60';
    // eslint-disable-next-line lingui/no-unlocalized-strings
    const iconWrapperBg = isCardDark ? 'bg-white/10 text-white' : 'bg-gray-50 text-gray-600';
    const subtleBtnBorder = isCardDark ? 'border-white/10' : 'border-gray-200';
    // eslint-disable-next-line lingui/no-unlocalized-strings
    const subtleBtnBg = isCardDark ? 'bg-black/40 hover:bg-black/60' : 'bg-white hover:bg-gray-50';
    // -----------------------------

    const coverImageData = eventCoverImage(event);
    const coverImage = coverImageData?.url;
    // eslint-disable-next-line @typescript-eslint/no-non-null-assertion
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

    const isImageBg = backgroundType === 'MIRROR_COVER_IMAGE';
    const isGradientBg = backgroundType === 'GRADIENT';

    return (
        <div className={`min-h-[100dvh] font-sans relative ${isCardDark ? 'selection:bg-white/20' : 'selection:bg-black/10'}`}>
            {isImageBg && coverImage ? (
                <div className="fixed inset-0 z-[-1] w-full h-full overflow-hidden" aria-hidden="true">
                    <div
                        className="absolute inset-0 bg-cover bg-center bg-no-repeat scale-125 blur-lg"
                        style={{ backgroundImage: `url(${coverImage})` }}
                    />
                    <div
                        className={`absolute inset-0 ${isCardDark ? 'bg-black/60' : 'bg-white/50'}`}
                        style={{ backgroundColor, opacity: 0.75 }}
                    />
                </div>
            ) : isGradientBg ? (
                <div
                    className="fixed inset-0 z-[-1] overflow-hidden"
                    style={{ backgroundColor }}
                    aria-hidden="true"
                >
                    <div className="absolute top-[-10%] left-[-10%] w-[50vw] h-[50vh] rounded-full opacity-70 animate-blob-1" style={{ backgroundColor: meshColors[0], filter: 'blur(100px)' }} />
                    <div className="absolute top-[20%] right-[-10%] w-[60vw] h-[60vh] rounded-full opacity-70 animate-blob-2" style={{ backgroundColor: meshColors[1], filter: 'blur(120px)' }} />
                    <div className="absolute bottom-[-20%] left-[10%] w-[55vw] h-[55vh] rounded-full opacity-70 animate-blob-3" style={{ backgroundColor: meshColors[2], filter: 'blur(110px)' }} />
                    <div className="absolute bottom-[-10%] right-[20%] w-[45vw] h-[45vh] rounded-full opacity-60 animate-blob-4" style={{ backgroundColor: meshColors[0], filter: 'blur(130px)' }} />
                </div>
            ) : (
                <div
                    className="fixed inset-0 z-[-1]"
                    style={{ backgroundColor }}
                    aria-hidden="true"
                />
            )}
            <style>
                {/* eslint-disable-next-line lingui/no-unlocalized-strings */}
                {`
                    body, .ssr-loader { background-color: ${backgroundColor} !important; --prose-accent: ${accentColor}; }
                    /* Form elements in dark/light mode */
                    .hi-widget-container { background: transparent !important; color: ${cardTextPrimary.replace('text-', '')} !important; }

                    @keyframes blob1 {
                        0% { transform: translate(0px, 0px) scale(1); }
                        33% { transform: translate(30vw, 10vh) scale(1.1); }
                        66% { transform: translate(-10vw, 20vh) scale(0.9); }
                        100% { transform: translate(0px, 0px) scale(1); }
                    }
                    @keyframes blob2 {
                        0% { transform: translate(0px, 0px) scale(1); }
                        33% { transform: translate(-25vw, -15vh) scale(0.9); }
                        66% { transform: translate(15vw, 25vh) scale(1.1); }
                        100% { transform: translate(0px, 0px) scale(1); }
                    }
                    @keyframes blob3 {
                        0% { transform: translate(0px, 0px) scale(1); }
                        33% { transform: translate(10vw, -25vh) scale(1.15); }
                        66% { transform: translate(-20vw, -10vh) scale(0.85); }
                        100% { transform: translate(0px, 0px) scale(1); }
                    }
                    @keyframes blob4 {
                        0% { transform: translate(0px, 0px) scale(1); }
                        33% { transform: translate(-15vw, 15vh) scale(0.95); }
                        66% { transform: translate(25vw, -15vh) scale(1.05); }
                        100% { transform: translate(0px, 0px) scale(1); }
                    }
                    .animate-blob-1 { animation: blob1 8s infinite ease-in-out; }
                    .animate-blob-2 { animation: blob2 13s infinite ease-in-out; }
                    .animate-blob-3 { animation: blob3 19s infinite ease-in-out; }
                    .animate-blob-4 { animation: blob4 21s infinite ease-in-out; }
                    .hi-widget-product { 
                        background: ${isCardDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.02)'} !important; 
                        border: 1px solid ${isCardDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.05)'} !important; 
                        border-radius: 0.75rem !important; 
                    }
                    .hi-widget-product:hover { 
                        background: ${isCardDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.04)'} !important; 
                        border-color: ${isCardDark ? 'rgba(255,255,255,0.2)' : 'rgba(0,0,0,0.1)'} !important; 
                    }
                    /* Prose Table Fallback */
                    .prose table { width: 100%; border-collapse: collapse; margin-block: 1.5rem; }
                    .prose th, .prose td { border: 1px solid ${isCardDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.1)'}; padding: 0.75rem; text-align: left; }
                    .prose th { font-weight: 600; background-color: ${isCardDark ? 'rgba(255,255,255,0.03)' : 'rgba(0,0,0,0.03)'}; }
                `}
            </style>

            {event && <EventDocumentHead event={event} />}

            <div className="max-w-[1000px] mx-auto px-4 sm:px-6 lg:px-8 pt-8 pb-24 relative z-10 flex flex-col md:flex-row gap-8 lg:gap-12 md:items-start mt-4">

                {/* Left Column - Fixed on Desktop */}
                <div className="md:w-[320px] shrink-0 flex flex-col gap-6 md:sticky md:top-8 z-20">
                    {/* Cover Image Card */}
                    {coverImage && (
                        <div className={`relative w-full rounded-2xl overflow-hidden shadow-sm border ${borderStyle} group ${isCardDark ? 'bg-gray-900' : 'bg-gray-100'}`}>
                            {coverImageData?.lqip_base64 && (
                                <img src={coverImageData.lqip_base64} alt="" aria-hidden="true" className="absolute inset-0 w-full h-full object-cover blur-md" />
                            )}
                            <img src={coverImage} alt={event.title} className="relative w-full h-auto block object-cover" />
                            <div className="absolute inset-0 bg-gradient-to-t from-black/10 to-transparent pointer-events-none" />

                            {/* Status Badge Over Image */}
                            {statusBadge && (
                                <div className="absolute top-3 left-3">
                                    <div className={`px-2.5 py-1 text-[11px] font-bold uppercase tracking-wider rounded-lg flex items-center gap-1.5 shadow-md border ${statusBadge.variant === 'danger' ? 'bg-red-50 text-red-600 border-red-100' : 'bg-gray-900/95 backdrop-blur-md text-white border-white/10'}`}>
                                        <IconTicket size={14} />
                                        {statusBadge.text}
                                    </div>
                                </div>
                            )}

                            <div className="absolute top-3 right-3 flex gap-2">
                                {/* eslint-disable-next-line lingui/no-unlocalized-strings */}
                                <ShareComponent title={'Check out this event: ' + event.title} text={'Check out this event: ' + event.title} url={eventHomepageUrl(event)} imageUrl={coverImage || undefined}>
                                    <button className="h-9 w-9 flex items-center justify-center rounded-lg bg-white/90 backdrop-blur-md border border-black/5 text-gray-900 hover:bg-white transition shadow-sm" title={t`Share`}>
                                        <IconShare size={16} />
                                    </button>
                                </ShareComponent>
                            </div>
                        </div>
                    )
                    }

                    {/* Organizer Card Desktop */}
                    {
                        organizer && organizer.status === OrganizerStatus.LIVE && (
                            <div className={`${cardBg} rounded-2xl p-3 hidden md:flex flex-col gap-4 border-none`}>
                                <div className={`text-xs font-semibold tracking-wider uppercase ${textSecondary}`}>{t`Presented By`}</div>
                                <div className="flex items-center gap-3">
                                    {organizerLogo ? (
                                        <img src={organizerLogo} alt={organizer.name} className={`w-10 h-10 rounded-full border ${borderStyle} ${isCardDark ? 'bg-gray-800' : 'bg-white'} object-cover`} />
                                    ) : (
                                        <div className={`w-10 h-10 rounded-full border ${borderStyle} ${isCardDark ? 'bg-gray-800 text-gray-300' : 'bg-gray-100 text-gray-600'} flex items-center justify-center text-lg font-bold`}>
                                            {organizer.name.charAt(0).toUpperCase()}
                                        </div>
                                    )}
                                    <div className="min-w-0">
                                        <Anchor href={organizerHomepageUrl(organizer)} className={`${linkTextPrimary} font-semibold text-base truncate block hover:opacity-70 transition-opacity`}>
                                            {organizer.name}
                                        </Anchor>
                                        {getShortLocationDisplay(organizerLocation) && (
                                            <div className={`${textSecondary} text-xs flex items-center gap-1 mt-0.5 truncate`}>
                                                {/* eslint-disable-next-line @typescript-eslint/no-non-null-assertion */}
                                                <a href={getGoogleMapsUrl(organizerLocation!)} target="_blank" rel="noopener noreferrer" className={`hover:${linkTextPrimary} ${linkTextSecondary} truncate`}>
                                                    {getShortLocationDisplay(organizerLocation)}
                                                </a>
                                            </div>
                                        )}
                                    </div>
                                </div>

                                {/* Organizer Actions */}
                                <div className="flex flex-col gap-2 mt-2">
                                    <button onClick={() => setContactModalOpen(true)} className="w-full py-2 px-3 rounded-lg text-sm font-medium transition flex justify-center items-center gap-2 shadow-sm hover:brightness-110" style={{ backgroundColor: accentColor, color: getContrastColor(accentColor) }}>
                                        <IconMail size={16} /> {t`Contact`}
                                    </button>

                                    <div className={`flex flex-wrap gap-2 ${(websiteUrl ? 1 : 0) + socialLinks.length > 4 ? 'justify-between' : 'justify-end'}`}>
                                        {websiteUrl && (
                                            <a href={websiteUrl} target="_blank" rel="noopener noreferrer" className={`h-10 w-10 flex items-center justify-center rounded-lg transition border shadow-none ${subtleBtnBg} ${subtleBtnBorder} ${linkTextPrimary}`}>
                                                <IconWorld size={18} />
                                            </a>
                                        )}
                                        {socialLinks.map(({ platform, handle, config }) => {
                                            const IconComponent = config.icon;
                                            return (
                                                <a key={platform} href={config.baseUrl + handle} target="_blank" rel="noopener noreferrer" className={`h-10 w-10 flex items-center justify-center rounded-lg transition border shadow-none ${subtleBtnBg} ${subtleBtnBorder} ${linkTextPrimary}`} title={platform}>
                                                    <IconComponent size={18} />
                                                </a>
                                            );
                                        })}
                                    </div>
                                </div>
                            </div>
                        )}

                    {event?.status && event?.id && (
                        <StatusToggle
                            entityType="event"
                            entityId={event.id}
                            currentStatus={event.status as 'DRAFT' | 'LIVE'}
                            entityName={event.title}
                            onSuccess={() => setTimeout(() => { window.location.reload(); }, 1000)}
                            className={`${cardBg} rounded-2xl p-4 flex flex-col gap-4 border-none shadow-sm`}
                            contentClassName="flex flex-col gap-3"
                            textClassName={`font-semibold text-[15px] text-center ${cardTextPrimary}`}
                            buttonClassName="w-full py-2.5 px-4 rounded-xl text-sm font-medium transition flex justify-center items-center gap-2 shadow-sm hover:brightness-[1.15]"
                            buttonStyle={{ backgroundColor: accentColor, color: getContrastColor(accentColor) }}
                        />
                    )}
                </div>

                {/* Right Column - Main Content */}
                <div className="flex-1 min-w-0 flex flex-col gap-10">

                    {/* Header Details */}
                    <div className="flex flex-col gap-6">
                        <h1 className={`text-3xl sm:text-4xl lg:text-[40px] font-extrabold tracking-tight leading-[1.15] ${bgTextPrimary} text-wrap`}>
                            {event.title}
                        </h1>

                        <div className="flex flex-col gap-4">
                            {/* Date Card */}
                            <div className={`p-4 flex items-start gap-4 transition-colors rounded-2xl ${cardBg} border ${borderStyle} ${cardHover}`}>
                                <div className={`p-2.5 rounded-xl ${iconWrapperBg} border ${borderStyle}`}>
                                    {event.end_date && isDateInPast(event.end_date) ? <IconCalendarOff size={22} className="opacity-70" /> : <IconCalendar size={22} className="opacity-70" />}
                                </div>
                                <div className="flex-1 min-w-0 pt-0.5">
                                    <div className={`font-semibold text-base mb-0.5 ${cardTextPrimary}`}>
                                        {event.end_date && isDateInPast(event.end_date) ? t`This event has ended` : <EventDateRange event={event} />}
                                    </div>
                                    <CalendarOptionsPopover event={event}>
                                        <button className={`text-sm font-medium ${textSecondary} hover:${linkTextPrimary} flex items-center gap-1.5 transition`}>
                                            <IconCalendarPlus size={16} /> {t`Add to Calendar`}
                                        </button>
                                    </CalendarOptionsPopover>
                                </div>
                            </div>

                            {/* Location Card */}
                            {hasLocation && locationDetails && (
                                <div className={`p-4 flex items-start gap-4 transition-colors rounded-2xl ${cardBg} border ${borderStyle} ${cardHover}`}>
                                    <div className={`p-2.5 rounded-xl ${iconWrapperBg} border ${borderStyle}`}>
                                        <IconMapPin size={22} className="opacity-70" />
                                    </div>
                                    <div className="flex-1 min-w-0 pt-0.5">
                                        <div className={`font-semibold text-base ${cardTextPrimary}`}>{locationDetails.venue_name}</div>
                                        <div className={`${cardTextSecondary} text-[15px] mt-0.5 truncate`}>{formatAddress(locationDetails)}</div>
                                        {mapUrl && (
                                            <a href={mapUrl} target="_blank" rel="noopener noreferrer" className={`text-sm font-medium mt-1 inline-flex items-center gap-1 transition ${linkTextSecondary} hover:${linkTextPrimary} hover:underline`}>
                                                {t`View Map`}
                                            </a>
                                        )}
                                    </div>
                                </div>
                            )}

                            {isOnlineEvent && (
                                <div className={`p-4 flex items-start gap-4 transition-colors rounded-2xl ${cardBg} border ${borderStyle} ${cardHover}`}>
                                    <div className={`p-2.5 rounded-xl ${iconWrapperBg} border ${borderStyle}`}>
                                        <IconWorld size={22} className="opacity-70" />
                                    </div>
                                    <div className="flex-1 min-w-0 pt-0.5">
                                        <div className={`font-semibold text-base ${cardTextPrimary}`}>{t`Online Event`}</div>
                                        <div className={`${cardTextSecondary} text-[15px] mt-0.5`}>{t`Join from anywhere`}</div>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Registration / Tickets Section */}
                    <div className={`${ticketCardBg} border ${borderStyle} rounded-2xl overflow-hidden shadow-sm relative`} ref={ticketsSectionRef} id="tickets">
                        {/* A very subtle top highlight line */}
                        <div className="absolute top-0 left-0 right-0 h-1 opacity-20" style={{ backgroundColor: accentColor }}></div>

                        <div className="p-6">
                            <h2 className={`text-xl font-bold mb-5 flex items-center gap-2 ${textPrimary}`}>
                                <IconTicket size={20} className="opacity-70" /> {t`Registration`}
                            </h2>
                            <div className="w-full">
                                <SelectProducts
                                    colors={{
                                        background: "transparent",
                                        primary: accentColor,
                                        primaryText: isCardDark ? "#ffffff" : "#111827",
                                        secondary: accentColor,
                                        secondaryText: getContrastColor(accentColor),
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
                            <h2 className={`text-xl font-bold mb-4 tracking-tight ${textPrimary}`}>
                                {t`About`}
                            </h2>
                            <div
                                className={`
                                    prose max-w-none 
                                    prose-p:leading-relaxed 
                                    prose-headings:font-bold 
                                    prose-a:text-[var(--prose-accent)] 
                                    hover:prose-a:brightness-110 
                                    transition-all duration-300
                                    ${isCardDark
                                        ? 'prose-invert text-white/80 prose-hr:border-white/20 prose-blockquote:text-white/80 prose-blockquote:border-white/30 marker:text-white/50'
                                        : 'text-gray-800 prose-hr:border-black/10 prose-blockquote:text-gray-600 prose-blockquote:border-black/10 marker:text-black/30'
                                    }
                                `}
                                dangerouslySetInnerHTML={{ __html: event.description }}
                            />
                        </div>
                    )}

                    {/* Location Details block below description */}
                    {hasLocation && locationDetails && (
                        <div className={`mt-8 pt-6 border-t ${borderStyle}`}>
                            <h2 className={`text-xl font-bold mb-4 tracking-tight ${textPrimary}`}>{t`Location`}</h2>
                            <div className="flex flex-col sm:flex-row gap-6">
                                <div className="flex-1 shrink-0">
                                    <h3 className={`text-lg font-semibold mb-1 ${textPrimary}`}>{locationDetails.venue_name}</h3>
                                    <p className={`whitespace-pre-line leading-relaxed mb-4 ${textSecondary}`}>{formatAddress(locationDetails)}</p>
                                    {mapUrl && (
                                        <a href={mapUrl} target="_blank" rel="noopener noreferrer" className={`inline-flex items-center gap-2 px-4 py-2 rounded-xl font-medium transition-colors border shadow-sm ${subtleBtnBg} ${subtleBtnBorder} ${linkTextPrimary}`}>
                                            {t`View on Google Maps`} <IconExternalLink size={16} />
                                        </a>
                                    )}
                                </div>
                                {mapUrl && (
                                    <a
                                        href={mapUrl}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className={`relative flex-1 rounded-xl overflow-hidden min-h-[140px] cursor-pointer group border ${borderStyle}`}
                                    >
                                        <svg
                                            viewBox="0 0 200 120"
                                            preserveAspectRatio="xMidYMid slice"
                                            className="absolute inset-0 w-full h-full"
                                        >
                                            <rect width="200" height="120" fill={isCardDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.04)'} />
                                            {/* River */}
                                            <path d="M-5 95 Q30 85, 50 90 Q80 100, 110 88 Q140 75, 170 82 Q190 86, 205 80" stroke={isCardDark ? 'rgba(255,255,255,0.5)' : 'rgba(0,0,0,0.35)'} strokeWidth="2" fill="none" opacity="0.5" />
                                            {/* Main roads */}
                                            <line x1="0" y1="50" x2="200" y2="50" stroke={isCardDark ? 'rgba(255,255,255,0.4)' : 'rgba(0,0,0,0.25)'} strokeWidth="2" opacity="0.8" />
                                            <line x1="100" y1="0" x2="100" y2="120" stroke={isCardDark ? 'rgba(255,255,255,0.4)' : 'rgba(0,0,0,0.25)'} strokeWidth="2" opacity="0.8" />
                                            {/* Secondary roads */}
                                            <line x1="0" y1="25" x2="200" y2="25" stroke={isCardDark ? 'rgba(255,255,255,0.25)' : 'rgba(0,0,0,0.15)'} strokeWidth="1.5" opacity="0.8" />
                                            <line x1="0" y1="70" x2="85" y2="70" stroke={isCardDark ? 'rgba(255,255,255,0.25)' : 'rgba(0,0,0,0.15)'} strokeWidth="1.5" opacity="0.8" />
                                            <line x1="115" y1="70" x2="200" y2="70" stroke={isCardDark ? 'rgba(255,255,255,0.25)' : 'rgba(0,0,0,0.15)'} strokeWidth="1.5" opacity="0.8" />
                                            <line x1="50" y1="0" x2="50" y2="120" stroke={isCardDark ? 'rgba(255,255,255,0.25)' : 'rgba(0,0,0,0.15)'} strokeWidth="1.5" opacity="0.8" />
                                            <line x1="150" y1="0" x2="150" y2="75" stroke={isCardDark ? 'rgba(255,255,255,0.25)' : 'rgba(0,0,0,0.15)'} strokeWidth="1.5" opacity="0.8" />
                                            {/* Blocks/buildings */}
                                            <rect x="110" y="28" width="14" height="10" fill={isCardDark ? 'rgba(255,255,255,0.2)' : 'rgba(0,0,0,0.15)'} opacity="1" rx="1" />
                                            <rect x="20" y="55" width="12" height="10" fill={isCardDark ? 'rgba(255,255,255,0.2)' : 'rgba(0,0,0,0.15)'} opacity="1" rx="1" />
                                        </svg>
                                        <IconMapPin
                                            size={32}
                                            className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-full drop-shadow-md"
                                            style={{ color: isCardDark ? '#ffffff' : '#111827' }}
                                        />
                                        <div className="absolute inset-0 flex items-end justify-center pb-3 bg-black/0 group-hover:bg-black/30 transition-colors duration-200">
                                            <span className="flex items-center gap-1.5 text-sm font-semibold px-3 py-1.5 rounded-lg bg-black/60 text-white opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                                <IconMaximize size={14} />
                                                {t`View Map`}
                                            </span>
                                        </div>
                                    </a>
                                )}
                            </div>
                        </div>
                    )}

                    {/* Organizer Card (Mobile View) */}
                    {organizer && organizer.status === OrganizerStatus.LIVE && (
                        <div className={`mt-8 pt-6 border-t md:hidden ${borderStyle}`}>
                            <h2 className={`text-xl font-bold mb-4 tracking-tight ${textPrimary}`}>{t`Presented By`}</h2>
                            <div className={`flex flex-col gap-4 p-0 border-none bg-transparent`}>
                                <div className="flex items-center gap-4">
                                    {organizerLogo ? (
                                        <img src={organizerLogo} alt={organizer.name} className={`w-12 h-12 rounded-full border ${borderStyle} ${isCardDark ? 'bg-gray-800' : 'bg-white'} object-cover`} />
                                    ) : (
                                        <div className={`w-12 h-12 rounded-full border ${borderStyle} ${isCardDark ? 'bg-gray-800 text-gray-300' : 'bg-gray-100 text-gray-600'} flex items-center justify-center text-xl font-bold`}>
                                            {organizer.name.charAt(0).toUpperCase()}
                                        </div>
                                    )}
                                    <div className="min-w-0">
                                        <Anchor href={organizerHomepageUrl(organizer)} className={`font-semibold text-lg truncate block hover:underline decoration-2 underline-offset-4 decoration-current/30 ${linkTextPrimary}`}>
                                            {organizer.name}
                                        </Anchor>
                                        {getShortLocationDisplay(organizerLocation) && (
                                            <div className={`${textSecondary} text-sm flex items-center gap-1 mt-0.5 truncate`}>
                                                {/* eslint-disable-next-line @typescript-eslint/no-non-null-assertion */}
                                                <a href={getGoogleMapsUrl(organizerLocation!)} target="_blank" rel="noopener noreferrer" className={`hover:${linkTextPrimary} ${linkTextSecondary} truncate`}>
                                                    {getShortLocationDisplay(organizerLocation)}
                                                </a>
                                            </div>
                                        )}
                                    </div>
                                </div>

                                {organizer.description && (
                                    <div className={`prose prose-sm line-clamp-3 mt-1 ${proseClasses}`} dangerouslySetInnerHTML={{ __html: organizer.description }} />
                                )}

                                <div className="flex flex-col gap-2 mt-2">
                                    <button onClick={() => setContactModalOpen(true)} className="w-full py-2.5 px-4 rounded-xl text-sm font-medium transition flex items-center justify-center gap-2 shadow-sm hover:brightness-110" style={{ backgroundColor: accentColor, color: getContrastColor(accentColor) }}>
                                        <IconMail size={16} /> {t`Contact`}
                                    </button>
                                    <div className="flex flex-wrap gap-2 justify-end">
                                        {websiteUrl && (
                                            <a href={websiteUrl} target="_blank" rel="noopener noreferrer" className={`h-[42px] w-[42px] flex items-center justify-center rounded-xl transition border shadow-sm ${subtleBtnBg} ${subtleBtnBorder} ${linkTextPrimary}`}>
                                                <IconWorld size={20} />
                                            </a>
                                        )}
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
                        </div>
                    )}
                </div>
            </div>

            {/* Footer */}
            <footer className={`max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 flex flex-col sm:flex-row items-center justify-between w-full pt-8 pb-8 mt-12 border-t ${isBgDark ? 'border-white/10' : 'border-black/5'}`}>

                {/* Left Side: Legal Links */}
                <div className="flex items-center gap-6 mb-4 sm:mb-0">
                    <a href={getConfig('VITE_PRIVACY_URL', 'https://hi.events/privacy-policy')} className={`text-sm transition-colors ${isBgDark ? '!text-white/70 hover:!text-white' : '!text-gray-600 hover:!text-gray-900'} !no-underline`} style={{ color: 'inherit' }}>
                        {t`Privacy Policy`}
                    </a>
                    <a href={getConfig('VITE_TOS_URL', 'https://hi.events/terms-of-service')} className={`text-sm transition-colors ${isBgDark ? '!text-white/70 hover:!text-white' : '!text-gray-600 hover:!text-gray-900'} !no-underline`} style={{ color: 'inherit' }}>
                        {t`Terms of Service`}
                    </a>
                </div>

                {/* Right Side: Branding */}
                <div className={`flex items-center text-sm ${isBgDark ? '!text-white/60' : '!text-gray-500'}`}>
                    <span>{t`Powered by`} </span>
                    <a href="https://hi.events" target="_blank" rel="noopener noreferrer" className={`font-semibold transition-colors ${isBgDark ? '!text-white hover:!text-white/80' : '!text-gray-900 hover:!text-gray-700'} !no-underline`} style={{ color: 'inherit' }}>
                        Hi.Events 🚀
                    </a>
                </div>

            </footer>

            <ContactOrganizerModal opened={contactModalOpen} onClose={() => setContactModalOpen(false)} organizer={organizer} />
        </div>
    );
};

export default EventHomepage;
