import {Event, IdParam} from "../types.ts";

export const eventCheckoutPath = (eventId: IdParam, orderShortId: IdParam, subPage = '') => {
    return `/checkout/${eventId}/${orderShortId}/${subPage}`;
}

export const eventHomepagePath = (event: Event) => {
    return `/event/${event?.id}/${event?.slug}`;
}

export const eventHomepageUrl = (event: Event) => {
    return import.meta.env.VITE_APP_FRONTEND_URL + eventHomepagePath(event);
}

export const eventCoverImageUrl = (event: Event) => {
    return event?.images?.find((image) => image.type === 'EVENT_COVER')?.url;
}
