import {LoaderFunctionArgs, redirect} from "react-router";
import {promoCodeClientPublic} from "../api/promo-code.client.ts";
import {getEventPublicQuery} from "../queries/useGetEventPublic.ts";
import {getQueryClient} from "../utilites/ssrQueryClient.ts";
import {loggableError} from "../ssr/loggableError.js";

const validatePromoCode = async (eventId: string | undefined, promoCode: string): Promise<boolean | undefined> => {
    try {
        const {valid} = await promoCodeClientPublic.validateCode(eventId, promoCode);
        return valid;
    } catch {
        return undefined;
    }
};

export const publicEventRouteLoader = async ({params, request}: LoaderFunctionArgs) => {
    try {
        const url = new URL(request.url);
        const queryParams = new URLSearchParams(url.search);
        const promoCode = queryParams.get("promo_code") ?? null;
        const occurrenceIdParam = queryParams.get("occurrence_id");
        const occurrenceId = occurrenceIdParam ? Number(occurrenceIdParam) : null;

        const promoCodeValid = promoCode ? await validatePromoCode(params.eventId, promoCode) : undefined;

        const eventQuery = getEventPublicQuery(
            params.eventId,
            promoCodeValid === undefined ? null : promoCode,
            promoCodeValid ?? false,
            occurrenceId,
        );

        const event = await getQueryClient().fetchQuery(eventQuery);

        if (event && event.slug && params.eventSlug !== event.slug) {
            const searchString = queryParams.toString();
            throw redirect(
                `/event/${event.id}/${event.slug}${searchString ? `?${searchString}` : ''}`
            );
        }

        return {event, promoCodeValid, promoCode, occurrenceId};
    } catch (error: any) {
        if (error instanceof Response) {
            throw error;
        }

        if (error?.response?.status === 404) {
            return {event: null, promoCodeValid: undefined, promoCode: null};
        }

        console.error(loggableError(error));
        throw error;
    }
};
