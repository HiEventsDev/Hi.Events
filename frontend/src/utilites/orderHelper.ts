import {Order} from "../types.ts";

export const isOfflineOrder = (order: Order): boolean =>
    order.payment_provider === 'OFFLINE'
    || (!order.payment_provider && order.is_manually_created);

export const isOrderRefundable = (order: Order): boolean =>
    !order.is_free_order
    && order.status !== 'AWAITING_OFFLINE_PAYMENT'
    && (order.payment_provider === 'STRIPE' || isOfflineOrder(order))
    && order.refund_status !== 'REFUNDED';
