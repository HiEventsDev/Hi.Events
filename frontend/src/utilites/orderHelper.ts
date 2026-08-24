import {Order} from "../types.ts";

export const isOrderRefundable = (order: Order): boolean =>
    !order.is_free_order
    && order.status !== 'AWAITING_OFFLINE_PAYMENT'
    && (order.payment_provider === 'STRIPE' || order.payment_provider === 'OFFLINE')
    && order.refund_status !== 'REFUNDED';

export const isOfflineOrder = (order: Order): boolean => order.payment_provider === 'OFFLINE';
