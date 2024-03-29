import {Event, IdParam} from "../types.ts";

export const eventCheckoutUrl = (eventId: IdParam, orderShortId: IdParam, subPage = '') => {
    return `/checkout/${eventId}/${orderShortId}/${subPage}`;
}

export const eventHomepageUrl = (event: Event) => {
    return `/event/${event?.id}/${event?.slug}`;
}
