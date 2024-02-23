import {Card} from "../Card";
import {getAttendeeTicketPrice, getAttendeeTicketTitle} from "../../../utilites/tickets.ts";
import {Anchor, Button} from "@mantine/core";
import {formatCurrency} from "../../../utilites/currency.ts";
import {t} from "@lingui/macro";
import {prettyDate} from "../../../utilites/dates.ts";
import QRCode from "react-qr-code";
import {IconCopy, IconDownload} from "@tabler/icons-react";
import {Attendee, Event, Ticket} from "../../../types.ts";
import classes from './AttendeeTicket.module.scss';

interface AttendeeTicketProps {
    event: Event;
    attendee: Attendee;
    ticket: Ticket;
}

export const AttendeeTicket = ({attendee, ticket, event}: AttendeeTicketProps) => {
    const ticketPrice = getAttendeeTicketPrice(attendee, ticket);

    return (
        <Card className={classes.attendee}>
            <div className={classes.attendeeInfo}>
                <div className={classes.attendeeNameAndPrice}>
                    <div className={classes.attendeeName}>
                        <h2>
                            {attendee.first_name} {attendee.last_name}
                        </h2>
                        <div>
                            {getAttendeeTicketTitle(attendee)}
                        </div>
                        <Anchor href={`mailto:${attendee.email}`}>
                            {attendee.email}
                        </Anchor>
                    </div>
                    <div className={classes.ticketPrice}>
                        <div className={classes.badge}>
                            {ticketPrice > 0 && formatCurrency(ticketPrice, event?.currency)}
                            {ticketPrice === 0 && t`Free`}
                        </div>
                    </div>
                </div>
                <div className={classes.eventInfo}>
                    <div className={classes.eventName}>
                        {event?.title}
                    </div>
                    <div className={classes.eventDate}>
                        {prettyDate(event.start_date, event.timezone)}
                    </div>
                </div>
            </div>
            <div className={classes.qrCode}>
                <div className={classes.attendeeCode}>
                    {attendee.public_id}
                </div>

                <div className={classes.qrImage}>
                    {attendee.status === 'CANCELLED' && (
                        <div className={classes.cancelled}>
                            {t`Cancelled`}
                        </div>
                    )}
                    {attendee.status !== 'CANCELLED' && <QRCode value={String(attendee.public_id)}/>}
                </div>
                <div className={classes.ticketButtons}>
                    <Button variant={'transparent'}
                            size={'sm'}
                            leftSection={<IconDownload size={18}/>
                            }>
                        {t`PDF`}
                    </Button>
                    <Button variant={'transparent'}
                            size={'sm'}
                            leftSection={<IconCopy size={18}/>
                            }>{
                        t`Copy Link`}
                    </Button>
                </div>
            </div>
        </Card>
    );
}