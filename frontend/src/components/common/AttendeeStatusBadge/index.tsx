import {Attendee} from "../../../types.ts";
import {Badge} from "@mantine/core";
import {t} from "@lingui/macro";

interface AttendeeStatusBadgeProps {
    attendee: Attendee;
    noStyle?: boolean;
}

export const AttendeeStatusBadge = ({attendee, noStyle = false}: AttendeeStatusBadgeProps) => {
    let color;

    switch (attendee.status) {
        case 'AWAITING_PAYMENT':
            color = 'orange';
            break;
        case 'CANCELLED':
            color = 'red';
            break;
        case 'ACTIVE':
        default:
            color = 'green';
            break;
    }

    const statusLabels: Record<string, string> = {
        'ACTIVE': t`Active`,
        'CANCELLED': t`Cancelled`,
        'AWAITING_PAYMENT': t`Awaiting Payment`,
    };

    const resaleStatus = attendee.resale?.status;
    const status = (resaleStatus === 'SOLD' && t`Resold`)
        || (resaleStatus === 'LISTED' && t`Listed for resale`)
        || statusLabels[attendee.status]
        || attendee.status.replace('_', ' ');

    if (resaleStatus === 'SOLD') {
        color = 'gray';
    } else if (resaleStatus === 'LISTED') {
        color = 'orange';
    }

    if (noStyle) {
        return <span style={{color: color}}>{status}</span>;
    }

    return (
        <Badge variant={'light'} color={color}>
            {status}
        </Badge>
    );
};
