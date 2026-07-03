import { useQuery } from '@tanstack/react-query';
import { orderClientPublic } from '../api/order.client';

export const useCreateRazorpayOrder = (eventId?: string, orderShortId?: string) => {
    return useQuery({
        queryKey: ['razorpay_order', eventId, orderShortId],
        queryFn: async () => {
            if (!eventId || !orderShortId) {
                return null;
            }
            return orderClientPublic.createRazorpayOrder(Number(eventId), orderShortId);
        },
        enabled: !!eventId && !!orderShortId,
        refetchOnWindowFocus: false,
        retry: false,
    });
};
