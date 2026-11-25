import {t} from "@lingui/macro";
import {TicketHistoryEntry} from "../../../types.ts";
import {prettyDate} from "../../../utilites/dates.ts";
import classes from "./TicketHistory.module.scss";

interface TicketHistoryProps {
    entries: TicketHistoryEntry[];
    timezone: string;
}

const describeAction = (action: string): string => {
    switch (action) {
        case 'ATTENDEE_UPDATED':
            return t`Ticket details changed by the buyer`;
        case 'ATTENDEE_UPDATED_BY_ORGANIZER':
            return t`Ticket details changed by an organizer`;
        case 'ORDER_UPDATED':
            return t`Order details changed by the buyer`;
        case 'ATTENDEE_EMAIL_RESENT':
            return t`Ticket email resent`;
        case 'ORDER_EMAIL_RESENT':
            return t`Order confirmation resent`;
        case 'AUTOMATIC_REFUND_SKIPPED':
            return t`Automatic refund skipped`;
        case 'AUTOMATIC_REFUND_FAILED':
            return t`Automatic refund failed`;
        case 'MANUAL_ATTENDEE_CAPACITY_OVERRIDE':
            return t`Added over capacity by an organizer`;
        case 'BOX_OFFICE_PRICE_ADJUSTED':
            return t`Price adjusted at the box office`;
        case 'BOX_OFFICE_ORDER_VOIDED':
            return t`Voided at the box office`;
        case 'BOX_OFFICE_EMAIL_UPDATED':
            return t`Email updated at the box office`;
        case 'TICKET_TRANSFER_STARTED':
            return t`Transfer started`;
        case 'TICKET_TRANSFER_CANCELLED':
            return t`Transfer cancelled by the sender`;
        case 'TICKET_TRANSFER_DECLINED':
            return t`Transfer declined by the recipient`;
        case 'TICKET_TRANSFER_ACCEPTED':
            return t`Transferred to a new holder`;
        case 'TICKET_RESALE_LISTED':
            return t`Listed for resale`;
        case 'TICKET_RESALE_WITHDRAWN':
            return t`Resale listing withdrawn by the buyer`;
        case 'TICKET_RESALE_CLOSED':
            return t`Resale listing closed`;
        case 'TICKET_RESALE_SOLD':
            return t`Resold to a new buyer`;
        case 'TICKET_RESALE_REFUNDED':
            return t`Resale refund sent to the buyer`;
        case 'TICKET_RESALE_REFUND_FAILED':
            return t`Resale refund failed`;
        case 'TICKET_RESALE_REFUND_RETRIED':
            return t`Resale refund retried`;
        default:
            return t`Updated`;
    }
};

const fieldLabel = (field: string): string | null => {
    switch (field) {
        case 'first_name':
            return t`First name`;
        case 'last_name':
            return t`Last name`;
        case 'email':
            return t`Email`;
        case 'phone':
            return t`Phone`;
        default:
            return null;
    }
};

const describeChanges = (entry: TicketHistoryEntry): string[] => {
    const changes = Object.entries(entry.new_values).flatMap(([field, value]) => {
        const label = fieldLabel(field);
        if (!label) {
            return [];
        }

        const previous = entry.old_values[field];

        return [previous ? `${label}: ${previous} → ${value ?? ''}` : `${label}: ${value ?? ''}`];
    });

    return entry.new_values.credentials_replaced
        ? [...changes, t`QR code and ticket link replaced`]
        : changes;
};

export const TicketHistory = ({entries, timezone}: TicketHistoryProps) => (
    <ol className={classes.list} aria-label={t`History`}>
        {entries.map((entry) => (
            <li key={entry.id} className={classes.entry}>
                <div className={classes.summary}>
                    <span className={classes.action}>{describeAction(entry.action)}</span>
                    <time className={classes.date} dateTime={entry.created_at}>
                        {prettyDate(entry.created_at, timezone)}
                    </time>
                </div>
                {describeChanges(entry).map((change) => (
                    <div key={change} className={classes.change}>{change}</div>
                ))}
            </li>
        ))}
    </ol>
);
