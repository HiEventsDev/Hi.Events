import {Event, EventLocation, EventOccurrence, LocationType} from "../types.ts";
import {formatAddress, getGoogleMapsUrl, getShortLocationDisplay, isAddressSet} from "./addressUtilities.ts";

export const resolveEventLocation = (event: Event, occurrence?: EventOccurrence | null): EventLocation | null => {
    return occurrence?.event_location ?? event?.event_location ?? null;
};

export interface EventLocationDisplay {
    isOnline: boolean;
    venueName: string | null;
    short: string | null;
    full: string | null;
    mapsUrl: string | null;
}

export const getEventLocationDisplay = (
    event: Event,
    occurrence?: EventOccurrence | null,
): EventLocationDisplay | null => {
    const location = resolveEventLocation(event, occurrence);
    if (!location) {
        return null;
    }

    if (location.type === LocationType.Online) {
        return {isOnline: true, venueName: null, short: null, full: null, mapsUrl: null};
    }

    const address = location.location?.structured_address ?? null;
    const venueName = (location.location?.name?.trim() || address?.venue_name?.trim()) || null;
    const short = getShortLocationDisplay(address) ?? venueName;
    const full = address && isAddressSet(address) ? formatAddress(address) : null;

    if (!venueName && !short && !full) {
        return null;
    }

    return {
        isOnline: false,
        venueName,
        short: short ?? venueName,
        full,
        mapsUrl: full ? getGoogleMapsUrl(address) : null,
    };
};
