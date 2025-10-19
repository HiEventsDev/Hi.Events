import {LoaderFunctionArgs, redirect} from "react-router";
import {promoCodeClientPublic} from "../api/promo-code.client.ts";
import {getEventPublicQuery} from "../queries/useGetEventPublic.ts";
import {getQueryClient} from "../utilites/ssrQueryClient.ts";

export const publicEventRouteLoader = async ({params, request}: LoaderFunctionArgs) => {
    try {
        const url = new URL(request.url);
        const queryParams = new URLSearchParams(url.search);
        const promoCode = queryParams.get("promo_code") ?? null;

        let promoCodeValid: boolean | undefined = undefined;

        if (promoCode) {
            const {valid} = await promoCodeClientPublic.validateCode(params.eventId, promoCode);
            promoCodeValid = valid;
        }

        const eventQuery = getEventPublicQuery(
            params.eventId,
            promoCode,
            promoCodeValid ?? false,
        );

        const event = await getQueryClient().fetchQuery(eventQuery);

        if (event && event.slug && params.eventSlug !== event.slug) {
            const searchString = queryParams.toString();
            throw redirect(
                `/event/${event.id}/${event.slug}${searchString ? `?${searchString}` : ''}`
            );
        }

        return {event, promoCodeValid, promoCode};
    } catch (error: any) {
        // Re-throw redirect responses so React Router can handle them
        if (error instanceof Response) {
            throw error;
        }

        if (error?.response?.status === 404) {
            return {event: null, promoCodeValid: undefined, promoCode: null};
        }

        console.error(error);
        throw error;
    }
};
