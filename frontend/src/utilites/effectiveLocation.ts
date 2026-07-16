import {Event, EventLocation, EventOccurrence, LocationType, VenueAddress} from "../types.ts";
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

export type EventLocationSummary =
    | {kind: 'none'}
    | {kind: 'single'; eventLocation: EventLocation; isEventDefault: boolean}
    | {kind: 'varied'; types: LocationType[]};

const locationIdentity = (eventLocation: EventLocation): string => {
    if (eventLocation.type === LocationType.Online) {
        return 'online';
    }
    if (eventLocation.location_id != null) {
        return `id:${eventLocation.location_id}`;
    }
    const location = eventLocation.location;
    return [
        location?.name ?? '',
        location?.structured_address?.venue_name ?? '',
        location?.structured_address ? formatAddress(location.structured_address) : '',
    ].join('|').trim().toLowerCase();
};

export const summariseEventLocations = (event: Event): EventLocationSummary => {
    const occurrences = event.occurrences ?? [];
    const resolvedList = occurrences.length > 0
        ? occurrences.map((occurrence) => resolveEventLocation(event, occurrence))
        : [resolveEventLocation(event, null)];
    const resolved = resolvedList.filter((location): location is EventLocation => location !== null);

    if (resolved.length === 0) {
        return {kind: 'none'};
    }

    const identities = new Set(resolved.map(locationIdentity));
    if (identities.size === 1 && resolved.length === resolvedList.length) {
        const eventLocation = resolved[0];
        const isEventDefault = event.event_location != null
            && locationIdentity(event.event_location) === locationIdentity(eventLocation);
        return {kind: 'single', eventLocation, isEventDefault};
    }

    return {kind: 'varied', types: [...new Set(resolved.map((location) => location.type))]};
};

const buildMapsUrl = (
    event: Event,
    allowCustomMapsUrl: boolean,
    eventLocation: EventLocation,
    fullAddress: VenueAddress | null,
): string | null => {
    const customMapsUrl = allowCustomMapsUrl ? event.settings?.maps_url?.trim() : null;
    if (customMapsUrl) {
        return customMapsUrl;
    }

    const latitude = eventLocation.location?.latitude;
    const longitude = eventLocation.location?.longitude;
    if (latitude != null && longitude != null) {
        return `https://www.google.com/maps?q=${latitude},${longitude}`;
    }

    return fullAddress ? getGoogleMapsUrl(fullAddress) : null;
};

export const buildEventLocationDisplay = (
    event: Event,
    eventLocation: EventLocation | null,
    allowCustomMapsUrl: boolean,
): EventLocationDisplay | null => {
    if (!eventLocation) {
        return null;
    }

    if (eventLocation.type === LocationType.Online) {
        return {isOnline: true, venueName: null, short: null, full: null, mapsUrl: null};
    }

    const address = eventLocation.location?.structured_address ?? null;
    const venueName = (eventLocation.location?.name?.trim() || address?.venue_name?.trim()) || null;
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
        mapsUrl: buildMapsUrl(event, allowCustomMapsUrl, eventLocation, full ? address : null),
    };
};

export const getEventLocationDisplay = (
    event: Event,
    occurrence?: EventOccurrence | null,
): EventLocationDisplay | null => {
    return buildEventLocationDisplay(
        event,
        resolveEventLocation(event, occurrence),
        !occurrence?.event_location,
    );
};
