import {Event, EventLocation, EventOccurrence} from "../types.ts";

export const resolveEventLocation = (event: Event, occurrence?: EventOccurrence | null): EventLocation | null => {
    return occurrence?.event_location ?? event?.event_location ?? null;
};
