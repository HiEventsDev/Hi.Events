import {getAttendeeProductPrice, getAttendeeProductTitle} from "../../../utilites/products.ts";
import {Button, CopyButton} from "@mantine/core";
import {formatCurrency} from "../../../utilites/currency.ts";
import {t} from "@lingui/macro";
import {prettyDate} from "../../../utilites/dates.ts";
import QRCode from "react-qr-code";
import {IconCopy, IconPrinter, IconLock, IconX} from "@tabler/icons-react";
import {Attendee, Event, EventOccurrence, LocationType, Product} from "../../../types.ts";
import classes from './AttendeeTicket.module.scss';
import {imageUrl} from "../../../utilites/urlHelper.ts";
import {resolveEventLocation} from "../../../utilites/effectiveLocation.ts";
import {formatAddress} from "../../../utilites/addressUtilities.ts";
import {PoweredByFooter} from "../PoweredByFooter";
import {EventDateRange} from "../EventDateRange";
import {ReactNode} from "react";

const DEFAULT_ACCENT_COLOR = '#6B46C1';

interface TicketFieldProps {
    label: string;
    value: ReactNode;
    meta?: ReactNode;
    span?: boolean;
    emphasis?: boolean;
}

const TicketField = ({label, value, meta, span, emphasis}: TicketFieldProps) => (
    <div className={[classes.field, span && classes.fieldSpan, emphasis && classes.fieldEmphasis]
        .filter(Boolean).join(' ')}>
        <dt className={classes.fieldLabel}>{label}</dt>
        <dd className={classes.fieldValue}>{value}</dd>
        {meta && <dd className={classes.fieldMeta}>{meta}</dd>}
    </div>
);

interface AttendeeTicketProps {
    event: Event;
    attendee: Attendee;
    product: Product;
    occurrence?: EventOccurrence;
    hideButtons?: boolean;
    showPoweredBy?: boolean;
}

export const AttendeeTicket = ({
                                   attendee,
                                   product,
                                   event,
                                   occurrence,
                                   hideButtons = false,
                                   showPoweredBy = false,
                               }: AttendeeTicketProps) => {
    const ticketOccurrence = attendee.event_occurrence ?? occurrence;
    const productPrice = getAttendeeProductPrice(attendee, product);
    const eventLocation = resolveEventLocation(event, ticketOccurrence);
    const venueName = eventLocation?.type === LocationType.InPerson
        ? (eventLocation.location?.name || eventLocation.location?.structured_address?.venue_name || null)
        : null;
    const formattedAddress = eventLocation?.type === LocationType.InPerson && eventLocation.location?.structured_address
        ? formatAddress(eventLocation.location.structured_address)
        : '';
    const isInPerson = eventLocation?.type === LocationType.InPerson && Boolean(venueName || formattedAddress);
    const isOnline = eventLocation?.type === LocationType.Online;

    const ticketDesignSettings = event?.settings?.ticket_design_settings;
    const accentColor = ticketDesignSettings?.accent_color || DEFAULT_ACCENT_COLOR;
    const footerText = ticketDesignSettings?.footer_text;
    const dateDisplayMode = ticketDesignSettings?.date_display_mode || 'START_DATE_TIME';
    const logoUrl = imageUrl('TICKET_LOGO', event?.images);

    const isCancelled = attendee.status === 'CANCELLED';
    const isAwaitingPayment = attendee.status === 'AWAITING_PAYMENT';
    const isVoid = isCancelled || isAwaitingPayment;
    const attendeeName = [attendee.first_name, attendee.last_name].filter(Boolean).join(' ');

    return (
        <div className={classes.ticketWrapper} style={{'--accent': accentColor} as React.CSSProperties}>
            <article className={`${classes.ticket} ${isVoid ? classes.ticketVoid : ''}`}>
                <header className={classes.header}>
                    {logoUrl && <img src={logoUrl} alt="" className={classes.logo}/>}

                    <div className={classes.headline}>
                        {event?.organizer?.name && (
                            <p className={classes.organizer}>{event.organizer.name}</p>
                        )}
                        <h2 className={classes.eventTitle}>{event?.title}</h2>
                    </div>

                    {isVoid && (
                        <span
                            className={`${classes.status} ${isCancelled ? classes.statusCancelled : classes.statusPending}`}>
                            {isCancelled ? t`Cancelled` : t`Unpaid`}
                        </span>
                    )}
                </header>

                <div className={classes.main}>
                    <div className={classes.info}>
                        <dl className={classes.fields}>
                            <TicketField
                                label={t`Attendee`}
                                value={attendeeName}
                                meta={attendee.email}
                                span
                                emphasis
                            />

                            {dateDisplayMode !== 'HIDDEN' && (
                                <TicketField
                                    label={t`Date & Time`}
                                    value={dateDisplayMode === 'DATE_RANGE'
                                        ? <EventDateRange event={event} occurrence={ticketOccurrence}/>
                                        : prettyDate(ticketOccurrence?.start_date ?? event.start_date, event.timezone, true)}
                                    meta={ticketOccurrence?.label}
                                />
                            )}

                            {isInPerson && (
                                <TicketField
                                    label={t`Location`}
                                    value={venueName || formattedAddress}
                                    meta={venueName ? formattedAddress : undefined}
                                />
                            )}

                            {isOnline && (
                                <TicketField label={t`Location`} value={t`Online event`}/>
                            )}

                            <TicketField label={t`Ticket`} value={getAttendeeProductTitle(attendee, product)}/>

                            <TicketField
                                label={t`Price`}
                                value={productPrice > 0 ? formatCurrency(productPrice, event?.currency) : t`Free`}
                            />
                        </dl>

                        {footerText && <p className={classes.note}>{footerText}</p>}
                    </div>

                    <aside className={classes.stub}>
                        <div className={`${classes.code} ${isVoid ? classes.codeVoid : ''}`}>
                            <div className={classes.codeInner}>
                                {isVoid ? (
                                    <div className={classes.codeMessage}>
                                        {isCancelled ? <IconX size={22} stroke={1.75}/> :
                                            <IconLock size={22} stroke={1.75}/>}
                                        <span>{isCancelled ? t`This ticket is no longer valid` : t`Available once payment completes`}</span>
                                    </div>
                                ) : (
                                    <QRCode
                                        value={String(attendee.public_id)}
                                        size={200}
                                        level="M"
                                        style={{height: "auto", width: "100%", display: "block"}}
                                    />
                                )}
                            </div>
                        </div>

                        <div className={classes.ticketId}>
                            <span className={classes.fieldLabel}>{t`Ticket ID`}</span>
                            <span className={classes.ticketIdValue}>{attendee.public_id}</span>
                        </div>
                    </aside>
                </div>
            </article>

            {!hideButtons && (
                <div className={classes.actions}>
                    <Button
                        variant="default"
                        size="sm"
                        onClick={() => window?.open(`/product/${event.id}/${attendee.short_id}/print`, '_blank')}
                        leftSection={<IconPrinter size={16}/>}
                    >
                        {t`Print to PDF`}
                    </Button>

                    <CopyButton value={`${window?.location.origin}/product/${event.id}/${attendee.short_id}`}>
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

            {showPoweredBy && (
                <div className={classes.poweredBy}>
                    <PoweredByFooter/>
                </div>
            )}
        </div>
    );
}
