import {LoaderFunctionArgs, redirect} from "react-router";
import {validatePromoCode} from "./validatePromoCode.ts";
import {getEventPublicQuery} from "../queries/useGetEventPublic.ts";
import {getEventLineupPublicQuery} from "../queries/useGetEventLineupPublic.ts";
import {getQueryClient} from "../utilites/ssrQueryClient.ts";
import {loggableError} from "../ssr/loggableError.js";

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

        const [event] = await Promise.all([
            getQueryClient().fetchQuery(eventQuery),
            getQueryClient().prefetchQuery(getEventLineupPublicQuery(params.eventId)),
        ]);

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
