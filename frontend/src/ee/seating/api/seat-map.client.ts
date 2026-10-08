import {AxiosError} from "axios";
import {api} from "../../../api/client.ts";
import {publicApi} from "../../../api/public-client.ts";
import {Attendee, GenericDataResponse, GenericPaginatedResponse, IdParam} from "../../../types.ts";
import {SeatMapLayout} from "../components/lib/types.ts";

export interface SeatMapBandProducts {
    band_key: string;
    products: {product_id: number; price_adjustment: number}[];
}

export interface PublicSeatMapBandProducts {
    band_key: string;
    products: {product_id: number}[];
}

export interface SeatMapSummary {
    id: number;
    organizer_id: number;
    name: string;
    version: number;
    seat_count: number;
    updated_at: string;
    preview_layout: SeatMapLayout;
}

export interface SeatMap extends SeatMapSummary {
    layout: SeatMapLayout;
}

export interface SeatMapDiff {
    added_seat_count: number;
    removed_seat_labels: string[];
    relabelled_seat_count: number;
    rebanded_seat_count: number;
}

export interface EventSeatMapRules {
    prevent_orphan_seats: boolean;
    max_seats_per_order: number | null;
    allow_seat_change: boolean;
}

export interface OccupiedSeat {
    seat_uid: string;
    seat_label: string;
    is_zone: boolean;
    band_key: string;
    status: 'HELD' | 'SOLD' | 'BLOCKED';
    block_reason: string | null;
    attendee_public_id: string | null;
    attendee_name: string | null;
}

export type SeatBlockDates = {all_upcoming_dates: true} | {event_occurrence_ids: IdParam[]};

export interface SeatBlockResult {
    blocked: number;
    skipped: {event_occurrence_id: number; seat_uids: string[]}[];
}

export interface SeatBlockReleaseResult {
    released: number;
}

export interface PublicEventSeatMap {
    version: number;
    layout: SeatMapLayout;
    band_products: PublicSeatMapBandProducts[];
    prevent_orphan_seats: boolean;
    max_seats_per_order: number | null;
    allow_seat_change: boolean;
}

export interface EventSeatMap extends PublicEventSeatMap {
    band_products: SeatMapBandProducts[];
    id: number;
    event_id: number;
    source_seat_map: {id: number; name: string} | null;
    is_update_available_from_source: boolean;
}

export interface SeatAvailability {
    version: number;
    unavailable_seat_uids: string[];
    zone_remaining: Record<string, number>;
    band_free: Record<string, number>;
}

export interface BestAvailableSeatsParams {
    product_id: number;
    quantity: number;
    accessible?: boolean;
    exclude?: string[];
}

export const seatMapClientPublic = {
    getSeatMap: async (eventId: IdParam) => {
        const response = await publicApi.get<GenericDataResponse<PublicEventSeatMap>>(`events/${eventId}/seat-map`);
        return response.data;
    },
    getAvailability: async (eventId: IdParam, occurrenceId: IdParam) => {
        const response = await publicApi.get<GenericDataResponse<SeatAvailability>>(
            `events/${eventId}/occurrences/${occurrenceId}/seat-availability`,
        );
        return response.data;
    },
    changeAttendeeSeat: async (eventId: IdParam, orderShortId: string, attendeeShortId: string, seatUid: string) => {
        const response = await publicApi.put<GenericDataResponse<Attendee>>(
            `events/${eventId}/order/${orderShortId}/attendees/${attendeeShortId}/seat`,
            {seat_uid: seatUid},
        );
        return response.data;
    },
    getBestAvailable: async (eventId: IdParam, occurrenceId: IdParam, params: BestAvailableSeatsParams) => {
        const response = await publicApi.get<GenericDataResponse<{seat_uids: string[]}>>(
            `events/${eventId}/occurrences/${occurrenceId}/best-available-seats`,
            {params: {...params, accessible: params.accessible ? 1 : 0}},
        );
        return response.data;
    },
};

export const seatMapClient = {
    all: async (organizerId: IdParam) => {
        const response = await api.get<GenericPaginatedResponse<SeatMapSummary>>(`organizers/${organizerId}/seat-maps`, {
            params: {per_page: 100},
        });
        return response.data;
    },
    create: async (organizerId: IdParam, name: string, layout: SeatMapLayout) => {
        const response = await api.post<GenericDataResponse<SeatMap>>(`organizers/${organizerId}/seat-maps`, {name, layout});
        return response.data;
    },
    get: async (organizerId: IdParam, seatMapId: IdParam) => {
        const response = await api.get<GenericDataResponse<SeatMap>>(`organizers/${organizerId}/seat-maps/${seatMapId}`);
        return response.data;
    },
    update: async (organizerId: IdParam, seatMapId: IdParam, name: string, layout: SeatMapLayout, version?: number) => {
        const response = await api.put<GenericDataResponse<SeatMap>>(`organizers/${organizerId}/seat-maps/${seatMapId}`, {
            name,
            layout,
            version,
        });
        return response.data;
    },
    delete: async (organizerId: IdParam, seatMapId: IdParam) => {
        await api.delete(`organizers/${organizerId}/seat-maps/${seatMapId}`);
    },
    getForEvent: async (eventId: IdParam) => {
        const response = await api.get<GenericDataResponse<EventSeatMap>>(`events/${eventId}/seat-map`);
        return response.data;
    },
    attach: async (eventId: IdParam, seatMapId: IdParam) => {
        const response = await api.post<GenericDataResponse<EventSeatMap>>(`events/${eventId}/seat-map`, {seat_map_id: seatMapId});
        return response.data;
    },
    detach: async (eventId: IdParam) => {
        await api.delete(`events/${eventId}/seat-map`);
    },
    updateLayout: async (eventId: IdParam, layout: SeatMapLayout, version?: number, confirmRelabel?: boolean) => {
        const response = await api.put<GenericDataResponse<EventSeatMap>>(`events/${eventId}/seat-map/layout`, {
            layout,
            version,
            confirm_relabel: confirmRelabel,
        });
        return response.data;
    },
    updateBandProducts: async (eventId: IdParam, bandProducts: SeatMapBandProducts[]) => {
        const response = await api.put<GenericDataResponse<EventSeatMap>>(`events/${eventId}/seat-map/band-products`, {
            band_products: bandProducts,
        });
        return response.data;
    },
    updateRules: async (eventId: IdParam, rules: EventSeatMapRules) => {
        const response = await api.put<GenericDataResponse<EventSeatMap>>(`events/${eventId}/seat-map/rules`, rules);
        return response.data;
    },
    getOccupiedSeats: async (eventId: IdParam, occurrenceId: IdParam) => {
        const response = await api.get<GenericDataResponse<OccupiedSeat[]>>(`events/${eventId}/occurrences/${occurrenceId}/occupied-seats`);
        return response.data;
    },
    blockSeats: async (eventId: IdParam, dates: SeatBlockDates, seatUids: string[], reason: string | null) => {
        const response = await api.post<GenericDataResponse<SeatBlockResult>>(`events/${eventId}/seat-blocks`, {...dates, seat_uids: seatUids, reason});
        return response.data.data;
    },
    releaseSeatBlocks: async (eventId: IdParam, dates: SeatBlockDates, seatUids: string[]) => {
        const response = await api.post<GenericDataResponse<SeatBlockReleaseResult>>(`events/${eventId}/seat-blocks/release`, {...dates, seat_uids: seatUids});
        return response.data.data;
    },
    moveAttendeeSeat: async (eventId: IdParam, attendeeId: IdParam, seatUid: string) => {
        const response = await api.put<GenericDataResponse<Attendee>>(`events/${eventId}/attendees/${attendeeId}/seat`, {seat_uid: seatUid});
        return response.data;
    },
    syncFromSource: async (eventId: IdParam, dryRun: boolean) => {
        const response = await api.post<GenericDataResponse<SeatMapDiff>>(`events/${eventId}/seat-map/sync-from-source`, {
            dry_run: dryRun,
        });
        return response.data;
    },
};

export interface RelabelConfirmation {
    message: string;
    relabelled_seat_labels: string[];
}

export const relabelConfirmationRequired = (error: unknown): RelabelConfirmation | null => {
    const data = (error as AxiosError<RelabelConfirmation & {requires_relabel_confirmation?: boolean}>).response?.data;
    return data?.requires_relabel_confirmation ? data : null;
};

