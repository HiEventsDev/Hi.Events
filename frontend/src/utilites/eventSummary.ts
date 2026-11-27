import {t} from "@lingui/macro";
import {Event, EventType, LocationType, Product} from "../types.ts";
import {summariseEventLocations} from "./effectiveLocation.ts";
import {formatAddress} from "./addressUtilities.ts";

export type EventStatusTone = 'success' | 'warning' | 'muted';

interface EventStatusSummary {
    label: string;
    status: 'archived' | 'ended' | 'draft' | 'live' | 'onsale';
    tone: EventStatusTone;
    pulse?: boolean;
}

interface EventTicketAvailability {
    text: string;
    status: 'sold-out' | 'available' | 'partial';
}

export const getEventStatusSummary = (event: Event): EventStatusSummary => {
    if (event.status === 'ARCHIVED') {
        return {label: t`Archived`, status: 'archived', tone: 'muted'};
    }
    if (event.lifecycle_status === 'ENDED') {
        return {label: t`Ended`, status: 'ended', tone: 'muted'};
    }
    if (event.status === 'PENDING_MANUAL_REVIEW') {
        return {label: t`Pending Review`, status: 'draft', tone: 'warning'};
    }
    if (event.status === 'DRAFT') {
        return {label: t`Draft`, status: 'draft', tone: 'warning'};
    }
    if (event.lifecycle_status === 'ONGOING') {
        return {label: t`Live`, status: 'live', tone: 'success', pulse: true};
    }
    return {label: t`On Sale`, status: 'onsale', tone: 'success'};
};

export const getEventLocationText = (event: Event): string | null => {
    const locationSummary = summariseEventLocations(event);
    if (locationSummary.kind === 'none') {
        return null;
    }
    if (locationSummary.kind === 'varied') {
        return locationSummary.types.length > 1 ? t`Online & in-person` : t`Multiple locations`;
    }
    const eventLocation = locationSummary.eventLocation;
    if (eventLocation.type === LocationType.Online) {
        return t`Online`;
    }
    const city = eventLocation.location?.structured_address?.city;
    const venueName = eventLocation.location?.name || eventLocation.location?.structured_address?.venue_name;
    const formatted = eventLocation.location?.structured_address ? formatAddress(eventLocation.location.structured_address) : '';
    return venueName ?? city ?? (formatted ? formatted : null);
};

export const getEventTicketAvailability = (event: Event): EventTicketAvailability | null => {
    const ticketProducts = (event.products ?? []).filter((product: Product) => product.product_type === 'TICKET');
    if (ticketProducts.length === 0) {
        return null;
    }

    const availableCount = ticketProducts.filter((product: Product) => {
        if (product.status === 'INACTIVE') {
            return false;
        }
        return product.is_available !== false && product.is_sold_out !== true;
    }).length;
    const totalCount = ticketProducts.length;

    if (availableCount === 0) {
        return {text: t`Sold out`, status: 'sold-out'};
    }
    if (availableCount === totalCount) {
        return {
            text: totalCount === 1 ? t`1 ticket type` : t`${totalCount} ticket types`,
            status: 'available',
        };
    }
    return {text: t`${availableCount} of ${totalCount} available`, status: 'partial'};
};

export const getEventDisplayDate = (event: Event): string =>
    (event.type === EventType.RECURRING && event.next_occurrence_start_date) || event.start_date;
