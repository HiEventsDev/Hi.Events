import {Badge, BadgeVariant} from "@mantine/core";
import {Order} from "../../../types.ts";
import {getStatusColor} from "../../../utilites/helpers.ts";
import {t} from "@lingui/macro";

const getStatusLabel = (status: string): string => {
    const labels: Record<string, string> = {
        'COMPLETED': t`Completed`,
        'CANCELLED': t`Cancelled`,
        'AWAITING_PAYMENT': t`Awaiting Payment`,
        'AWAITING_OFFLINE_PAYMENT': t`Awaiting offline payment`,
        'PAYMENT_RECEIVED': t`Payment Received`,
        'NO_PAYMENT_REQUIRED': t`No Payment Required`,
        'PAYMENT_FAILED': t`Payment Failed`,
        'REFUNDED': t`Refunded`,
        'PARTIALLY_REFUNDED': t`Partially Refunded`,
        'REFUND_PENDING': t`Refund Pending`,
    };
    return labels[status] || status.replace('_', ' ');
};

export const OrderStatusBadge = ({order, variant = 'outline'}: { order: Order, variant?: BadgeVariant }) => {
    let color;
    let title;

    if (order.status === 'CANCELLED') {
        color = getStatusColor(order.status);
        title = getStatusLabel(order.status);
    } else if (order.status === 'AWAITING_OFFLINE_PAYMENT') {
        color = getStatusColor('AWAITING_PAYMENT');
        title = getStatusLabel(order.status);
    } else if (order.refund_status) {
        color = getStatusColor(order.refund_status);
        title = getStatusLabel(order.refund_status);
    } else if (order.payment_status && order.payment_status !== 'PAYMENT_RECEIVED'
        && order.payment_status !== 'NO_PAYMENT_REQUIRED') {
        color = getStatusColor(order.payment_status);
        title = getStatusLabel(order.payment_status);
    } else {
        color = getStatusColor(order.status);
        title = getStatusLabel(order.status);
    }

    return <Badge color={color} variant={variant}>{title}</Badge>
};
