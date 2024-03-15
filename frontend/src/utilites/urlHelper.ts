import {Event, IdParam} from "../types.ts";

export const eventCheckoutUrl = (eventId: IdParam, orderShortId: IdParam, subPage: string = '') => {
    return window.location.origin + `/checkout/${eventId}/${orderShortId}/${subPage}`;
}

export const eventHomepageUrl = (event: Event) => {
    return window.location.origin + `/${event.id}/${event.slug}`;
}
