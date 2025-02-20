import {Badge, BadgeVariant} from "@mantine/core";
import {Order} from "../../../types.ts";
import {getStatusColor} from "../../../utilites/helpers.ts";
import {t} from "@lingui/macro";

export const OrderStatusBadge = ({order, variant = 'outline'}: { order: Order, variant?: BadgeVariant }) => {
    let color;
    let title;

    if (order.status === 'CANCELLED') {
        color = getStatusColor(order.status);
        title = t`Cancelled`;
    } else if (order.status === 'AWAITING_OFFLINE_PAYMENT') {
        color = getStatusColor('AWAITING_PAYMENT');
        title = t`Awaiting offline payment`;
    } else if (order.refund_status) {
        color = getStatusColor(order.refund_status);
        title = order.refund_status;
    } else if (order.payment_status && order.payment_status !== 'PAYMENT_RECEIVED'
        && order.payment_status !== 'NO_PAYMENT_REQUIRED') {
        color = getStatusColor(order.payment_status);
        title = order.payment_status;
    } else {
        color = getStatusColor(order.status);
        title = order.status;
    }

    return <Badge color={color} variant={variant}>{title.replace('_', ' ')}</Badge>
};
