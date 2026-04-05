import {useQuery} from '@tanstack/react-query';
import {IdParam} from '../types.ts';
import {orderClientPublic} from '../api/order.client.ts';

export const CHECK_DUPLICATE_ORDER_QUERY_KEY = 'checkDuplicateOrder';

export const useCheckDuplicateOrder = (eventId: IdParam, email: string | undefined) => {
    return useQuery({
        queryKey: [CHECK_DUPLICATE_ORDER_QUERY_KEY, eventId, email],
        queryFn: async () => {
            const response = await orderClientPublic.checkDuplicate(eventId, email!);
            return response;
        },
        enabled: !!eventId && !!email && email.includes('@'),
        staleTime: 60000,
    });
};
