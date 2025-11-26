import {useMutation, useQueryClient} from "@tanstack/react-query";
import {orderClientPublic, ProductFormPayload} from "../api/order.client.ts";
import {IdParam} from "../types.ts";
import {startOidcLogin} from "../utilites/oidcLogin.ts";

export const useCreateOrderPublic = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({orderData, eventId}: {
            orderData: ProductFormPayload,
            eventId: IdParam,
        }) => orderClientPublic.create(Number(eventId), orderData),

        onSuccess: () => queryClient.invalidateQueries(),
        onError: (error: any) => {
            if (error?.response?.status === 401) {
                startOidcLogin(window?.location?.href);
            }
        }
    });
}
