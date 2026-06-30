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

    const status = statusLabels[attendee.status] || attendee.status.replace('_', ' ');

    if (noStyle) {
        return <span style={{color: color}}>{status}</span>;
    }

    return (
        <Badge variant={'light'} color={color}>
            {status}
        </Badge>
    );
};
