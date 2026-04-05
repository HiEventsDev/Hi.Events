import {api} from "./client";
import {GenericDataResponse, IdParam} from "../types";

export interface SeatingChartSection {
    id?: number;
    seating_chart_id?: number;
    name: string;
    label?: string;
    color: string;
    capacity: number;
    row_count: number;
    seats_per_row: number;
    position: { x: number; y: number };
    shape: 'rectangle' | 'arc' | 'circle';
    sort_order?: number;
    seats?: SeatData[];
}

export interface SeatData {
    id?: number;
    seating_section_id?: number;
    seating_chart_id?: number;
    row_label: string;
    seat_number: number;
    label?: string;
    status: 'available' | 'reserved' | 'held' | 'sold' | 'disabled';
    attendee_id?: number | null;
    product_id?: number | null;
    price_override?: number | null;
    category?: string | null;
    position?: { x: number; y: number } | null;
    is_accessible?: boolean;
    is_aisle?: boolean;
}

export interface SeatingChartData {
    id?: number;
    event_id?: number;
    name: string;
    description?: string | null;
    layout?: Record<string, unknown> | null;
    total_seats: number;
    is_active: boolean;
    sections?: SeatingChartSection[];
    seats?: SeatData[];
    seats_by_section?: Record<number, SeatData[]>;
    created_at?: string;
    updated_at?: string;
}

export interface SeatingChartRequest {
    name: string;
    description?: string | null;
    layout?: Record<string, unknown> | null;
    total_seats: number;
    is_active?: boolean;
    sections?: Omit<SeatingChartSection, 'id' | 'seating_chart_id' | 'seats'>[];
}

export const seatingChartClient = {
    create: async (eventId: IdParam, data: SeatingChartRequest) => {
        const response = await api.post<GenericDataResponse<SeatingChartData>>(
            `events/${eventId}/seating-charts`, data
        );
        return response.data;
    },
    all: async (eventId: IdParam) => {
        const response = await api.get<GenericDataResponse<SeatingChartData[]>>(
            `events/${eventId}/seating-charts`
        );
        return response.data;
    },
    get: async (eventId: IdParam, chartId: IdParam) => {
        const response = await api.get<GenericDataResponse<SeatingChartData>>(
            `events/${eventId}/seating-charts/${chartId}`
        );
        return response.data;
    },
    assignSeat: async (eventId: IdParam, chartId: IdParam, seatId: IdParam, data: { attendee_id: number; product_id?: number }) => {
        const response = await api.post<GenericDataResponse<SeatData>>(
            `events/${eventId}/seating-charts/${chartId}/seats/${seatId}/assign`, data
        );
        return response.data;
    },
};
