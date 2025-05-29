import {Event, IdParam, Organizer} from "../types.ts";
import {getConfig} from "./config.ts";

export const eventCheckoutPath = (eventId: IdParam, orderShortId: IdParam, subPage = '') => {
    return `/checkout/${eventId}/${orderShortId}/${subPage}`;
}

export const eventPreviewPath = (eventId: IdParam) => {
    return `/event/${eventId}/preview`;
}

export const eventHomepagePath = (event: Event) => {
    return `/event/${event?.id}/${event?.slug}`;
}

export const organizerHomepagePath = (organizer: Organizer) => {
    return `/events/${organizer?.id}/${organizer?.slug}`;
}

export const organizerHomepageUrl = (organizer: Organizer) => {
    return getConfig('VITE_FRONTEND_URL') + organizerHomepagePath(organizer);
}

export const eventHomepageUrl = (event: Event) => {
    return getConfig('VITE_FRONTEND_URL') + eventHomepagePath(event);
}

export const eventCoverImageUrl = (event: Event) => {
    return event?.images?.find((image) => image.type === 'EVENT_COVER')?.url;
}

export const organizerPreviewPath = (organizerId: IdParam) => {
    return `/organizer/${organizerId}/preview`;
}
