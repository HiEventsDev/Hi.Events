import {Attendee} from "../../../types.ts";
import {Badge} from "@mantine/core";

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

    const status = attendee.status.replace('_', ' ');

    if (noStyle) {
        return <span style={{color: color}}>{status}</span>;
    }

    return (
        <Badge variant={'light'} color={color}>
            {status}
        </Badge>
    );
};
