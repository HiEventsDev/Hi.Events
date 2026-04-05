import {publicApi} from "./public-client.ts";
import {GenericDataResponse, IdParam, CheckoutStepConfig} from "../types";

export const checkoutConfigClientPublic = {
    get: async (eventId: IdParam) => {
        const response = await publicApi.get<GenericDataResponse<{
            multi_step_checkout_enabled: boolean;
            checkout_steps_config: CheckoutStepConfig[] | null;
        }>>(
            `events/${eventId}/checkout-config`
        );
        return response.data;
    },
};
