import {useMutation, useQueryClient} from "@tanstack/react-query";
import {cashlessClient} from "../api/cashless.client.ts";
import {CashlessSettings, IdParam} from "../types.ts";
import {GET_CASHLESS_SETTINGS_QUERY_KEY} from "../queries/useGetCashlessSettings.ts";
import {GET_EVENT_SETTINGS_QUERY_KEY} from "../queries/useGetEventSettings.ts";

type SettingsPayload = Omit<CashlessSettings, 'event_id' | 'cashless_topup_product_id' | 'cashless_closed_at'>;

export const useUpdateCashlessSettings = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, settings}: { eventId: IdParam, settings: SettingsPayload }) =>
            cashlessClient.updateSettings(eventId, settings),

        onSuccess: (_, variables) => Promise.all([
            queryClient.invalidateQueries({queryKey: [GET_CASHLESS_SETTINGS_QUERY_KEY, variables.eventId]}),
            queryClient.invalidateQueries({queryKey: [GET_EVENT_SETTINGS_QUERY_KEY, variables.eventId]}),
        ]),
    });
};
