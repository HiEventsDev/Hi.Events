import {Event, EventSettings, LocationType} from "../types.ts";
import {summariseEventLocations} from "./effectiveLocation.ts";
import {formatAddress} from "./addressUtilities.ts";
import {formatDateWithLocale} from "./dates.ts";

const DESCRIPTION_PREVIEW_LENGTH = 100;

const descriptionPreview = (html: string | null | undefined): string => {
    const text = (html ?? '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();

    return text.length > DESCRIPTION_PREVIEW_LENGTH
        ? text.slice(0, DESCRIPTION_PREVIEW_LENGTH - 3) + '...'
        : text;
};

export const eventPlaceName = (event: Event): string | null => {
    const locationSummary = summariseEventLocations(event);
    const effective = locationSummary.kind === 'single' ? locationSummary.eventLocation : null;

    if (effective?.type !== LocationType.InPerson) {
        return null;
    }

    const structuredAddress = effective.location?.structured_address;
    const venueName = effective.location?.name || structuredAddress?.venue_name || null;

    return venueName ?? (structuredAddress ? formatAddress(structuredAddress) : null);
};

export const eventMetaDescription = (event: Event, settings: EventSettings | undefined = event.settings): string => {
    const providedDescription = settings?.seo_description
        ?? event.description_preview
        ?? descriptionPreview(event.description);

    if (providedDescription) {
        return providedDescription;
    }

    const seriesStartDate = event.next_occurrence_start_date || event.start_date;

    return [
        seriesStartDate ? formatDateWithLocale(seriesStartDate, 'fullDateTime', event.timezone) : null,
        eventPlaceName(event),
        event.organizer?.name,
    ].filter(Boolean).join(' · ');
};
