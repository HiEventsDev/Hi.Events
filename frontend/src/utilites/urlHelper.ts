import {Event, IdParam, ImageType, Organizer, Image} from "../types.ts";
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

export const imageUrl = (imageType: ImageType, images?: Image[], fallbackUrl?: string) => {
    if (!images || images.length === 0) {
        return fallbackUrl || getConfig('VITE_DEFAULT_IMAGE_URL');
    }

    const image = images.find((img) => img.type === imageType);
    if (image) {
        return image.url;
    }
    return fallbackUrl || getConfig('VITE_DEFAULT_IMAGE_URL');
}

export const organizerPreviewPath = (organizerId: IdParam) => {
    return `/organizer/${organizerId}/preview`;
}
