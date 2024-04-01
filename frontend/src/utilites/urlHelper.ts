import {Event, IdParam} from "../types.ts";

export const eventCheckoutUrl = (eventId: IdParam, orderShortId: IdParam, subPage = '') => {
    return `/checkout/${eventId}/${orderShortId}/${subPage}`;
}

export const eventHomepageUrl = (event: Event) => {
    return `/event/${event?.id}/${event?.slug}`;
}

export const eventCoverImageUrl = (event: Event) => {
    return event?.images?.find((image) => image.type === 'EVENT_COVER')?.url;
}
