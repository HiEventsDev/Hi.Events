import {getAttendeeProductPrice, getAttendeeProductTitle} from "../../../utilites/products.ts";
import {Button, CopyButton} from "@mantine/core";
import {formatCurrency} from "../../../utilites/currency.ts";
import {t} from "@lingui/macro";
import {prettyDate} from "../../../utilites/dates.ts";
import QRCode from "react-qr-code";
import {IconCopy, IconPrinter} from "@tabler/icons-react";
import {Address, Attendee, Event, Product} from "../../../types.ts";
import classes from './AttendeeTicket.module.scss';
import {imageUrl} from "../../../utilites/urlHelper.ts";
import {formatAddress} from "../../../utilites/addressUtilities.ts";
import {PoweredByFooter} from "../PoweredByFooter";

interface AttendeeTicketProps {
    event: Event;
    attendee: Attendee;
    product: Product;
    hideButtons?: boolean;
    showPoweredBy?: boolean;
}

export const AttendeeTicket = ({
                                   attendee,
                                   product,
                                   event,
                                   hideButtons = false,
                                   showPoweredBy = false,
                               }: AttendeeTicketProps) => {
    const productPrice = getAttendeeProductPrice(attendee, product);
    const hasVenue = event?.settings?.location_details?.venue_name || event?.settings?.location_details?.address_line_1;

    const ticketDesignSettings = event?.settings?.ticket_design_settings;
    const accentColor = ticketDesignSettings?.accent_color || '#6B46C1';
    const footerText = ticketDesignSettings?.footer_text;
    const logoUrl = imageUrl('TICKET_LOGO', event?.images);

    const ticketStyle = {
        '--accent': accentColor,
    } as React.CSSProperties;

    const isCancelled = attendee.status === 'CANCELLED';
    const isAwaitingPayment = attendee.status === 'AWAITING_PAYMENT';

    return (
        <div className={classes.ticket} style={ticketStyle}>
            {/* Header */}
            <div className={classes.header}>
                <div className={classes.headerContent}>
                    <h1 className={classes.eventTitle}>{event?.title}</h1>
                    <div className={classes.priceDisplay}>
                        {productPrice > 0 ? formatCurrency(productPrice, event?.currency) : t`Free`}
                    </div>
                </div>
            </div>

            {/* Main Content */}
            <div className={classes.content}>
                <div className={classes.contentLeft}>
                    {/* Event Details */}
                    <div className={classes.eventDetails}>
                        <div className={classes.detailRow}>
                            <div className={classes.detailLabel}>{t`Date & Time`}</div>
                            <div className={classes.detailValue}>
                                {prettyDate(event.start_date, event.timezone, true)}
                            </div>
                        </div>
                        {event?.organizer?.name && (
                            <div className={classes.detailRow}>
                                <div className={classes.detailLabel}>{t`Organizer`}</div>
                                <div className={classes.detailValue}>
                                    {event?.organizer?.name}
                                </div>
                            </div>
                        )}

                        {hasVenue && (
                            <div className={classes.detailRow}>
                                <div className={classes.detailLabel}>{t`Location`}</div>
                                <div className={classes.detailValue}>
                                    {formatAddress(event?.settings?.location_details as Address)}
                                </div>
                            </div>
                        )}

                        <div className={classes.detailRow}>
                            <div className={classes.detailLabel}>{t`Ticket Type`}</div>
                            <div className={classes.detailValue}>
                                {getAttendeeProductTitle(attendee, product)}
                            </div>
                        </div>
                    </div>

                    {/* Attendee Information */}
                    <div className={classes.attendeeSection}>
                        <div className={classes.detailLabel}>{t`Attendee`}</div>
                        <div className={classes.attendeeName}>
                            {attendee.first_name} {attendee.last_name}
                        </div>
                        <div className={classes.attendeeEmail}>{attendee.email}</div>
                    </div>

                </div>

                {/* Right Section - Logo & QR Code */}
                <div className={classes.contentRight}>
                    <div className={classes.qrSection}>
                        {logoUrl && (
                            <div className={classes.logoContainer}>
                                <img src={logoUrl} alt="Event Logo" className={classes.logo}/>
                            </div>
                        )}

                        <div
                            className={classes.qrContainer}
                            style={{borderColor: accentColor}}
                        >
                            {(isCancelled || isAwaitingPayment) ? (
                                <div className={classes.statusOverlay}>
                                    <span className={isCancelled ? classes.cancelled : classes.pending}>
                                        {isCancelled ? t`Cancelled` : t`Awaiting Payment`}
                                    </span>
                                </div>
                            ) : (
                                <QRCode
                                    value={String(attendee.public_id)}
                                    size={180}
                                    level="M"
                                    style={{height: "auto", maxWidth: "100%", width: "100%"}}
                                />
                            )}
                        </div>

                        <div className={classes.ticketId}>
                            <div className={classes.detailLabel}>{t`Ticket ID`}</div>
                            <div
                                className={classes.ticketIdValue}
                                style={{color: accentColor}}
                            >{attendee.public_id}</div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Footer - Only show if there's footer text or buttons */}
            {(footerText || !hideButtons) && (
                <div className={classes.footer}>
                    <div className={classes.footerContent}>
                        {footerText && (
                            <div className={classes.footerText}>
                                {footerText}
                            </div>
                        )}

                        {!hideButtons && (
                            <div className={classes.actions}>
                                <Button
                                    variant="default"
                                    size="sm"
                                    onClick={() => window?.open(`/product/${event.id}/${attendee.short_id}/print`, '_blank')}
                                    leftSection={<IconPrinter size={16}/>}
                                >
                                    {t`Print`}
                                </Button>

                                <CopyButton
                                    value={`${window?.location.origin}/product/${event.id}/${attendee.short_id}`}>
                                    {({copied, copy}) => (
                                        <Button
                                            variant="default"
                                            size="sm"
                                            onClick={copy}
                                            leftSection={<IconCopy size={16}/>}
                                        >
                                            {copied ? t`Copied` : t`Copy Link`}
                                        </Button>
                                    )}
                                </CopyButton>
                            </div>
                        )}
                    </div>
                </div>
            )}

            {/* Powered By - Only shown in print mode */}
            {showPoweredBy && (
                <div className={classes.poweredByInTicket}>
                    <PoweredByFooter/>
                </div>
            )}
        </div>
    );
}
