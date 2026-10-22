import {LoaderFunctionArgs, redirect} from "react-router";
import {validatePromoCode} from "./validatePromoCode.ts";
import {getEventPublicQuery} from "../queries/useGetEventPublic.ts";
import {getEventLineupPublicQuery} from "../queries/useGetEventLineupPublic.ts";
import {getOrganizerPublicEventsQuery, UPCOMING_EVENTS_PREVIEW_FILTERS} from "../queries/useGetOrganizerEventsPublic.ts";
import {getQueryClient} from "../utilites/ssrQueryClient.ts";
import {resolveHomepageSections} from "../utilites/homepageSections.ts";
import {OrganizerStatus} from "../types.ts";
import {defaultOccurrence} from "../utilites/occurrenceDefaults.ts";
import {loggableError} from "../ssr/loggableError.js";

export const publicEventRouteLoader = async ({params, request}: LoaderFunctionArgs) => {
    try {
        const url = new URL(request.url);
        const queryParams = new URLSearchParams(url.search);
        const promoCode = queryParams.get("promo_code") ?? null;
        const occurrenceIdParam = queryParams.get("occurrence_id");
        const occurrenceId = occurrenceIdParam ? Number(occurrenceIdParam) : null;

        const promoCodeValid = promoCode ? await validatePromoCode(params.eventId, promoCode) : undefined;

        const eventQueryFor = (scopedOccurrenceId: number | null) => getEventPublicQuery(
            params.eventId,
            promoCodeValid === undefined ? null : promoCode,
            promoCodeValid ?? false,
            scopedOccurrenceId,
        );

        const [requestedEvent] = await Promise.all([
            getQueryClient().fetchQuery(eventQueryFor(occurrenceId)),
            getQueryClient().prefetchQuery(getEventLineupPublicQuery(params.eventId)),
        ]);
        if (requestedEvent && requestedEvent.slug && params.eventSlug !== requestedEvent.slug) {
            const searchString = queryParams.toString();
            throw redirect(
                `/event/${requestedEvent.id}/${requestedEvent.slug}${searchString ? `?${searchString}` : ''}`
            );
        }

        const defaultDate = occurrenceId === null && requestedEvent ? defaultOccurrence(requestedEvent) : undefined;
        const event = defaultDate
            ? await getQueryClient().fetchQuery(eventQueryFor(Number(defaultDate.id)))
            : requestedEvent;

        const showsMoreEvents = event?.organizer?.id
            && event.organizer.status === OrganizerStatus.LIVE
            && resolveHomepageSections(event.settings?.homepage_sections)
                .some(section => section.type === 'MORE_EVENTS' && section.enabled);
        if (showsMoreEvents) {
            await getQueryClient().prefetchQuery(getOrganizerPublicEventsQuery(event.organizer!.id!, UPCOMING_EVENTS_PREVIEW_FILTERS));
        }

        return {event, promoCodeValid, promoCode, occurrenceId: defaultDate ? Number(defaultDate.id) : occurrenceId};
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
