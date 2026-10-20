import {publicApi} from "../../../api/public-client";
import {
    BoxOfficeCatalogue,
    BoxOfficeOrderRequest,
    BoxOfficePublic,
    BoxOfficeSession,
    BoxOfficeTenderRequest,
    GenericDataResponse,
    GenericPaginatedResponse,
    IdParam,
    Order,
    PublicCheckIn,
    QueryFilters,
} from "../../../types";
import {queryParamsHelper} from "../../../utilites/queryParamsHelper";
import {OccupiedSeat, PublicEventSeatMap} from "../../seating/api/seat-map.client";
import {BOX_OFFICE_SESSION_HEADER} from "../utilites/boxOfficeSession";

export const publicBoxOfficeClient = {
    get: async (boxOfficeShortId: IdParam) => {
        const response = await publicApi.get<GenericDataResponse<BoxOfficePublic>>(`/box-offices/${boxOfficeShortId}`);
        return response.data;
    },
    createSession: async (boxOfficeShortId: IdParam, payload: { operator_name: string; pin: string | null; event_occurrence_id?: number | null; stripe_terminal_reader_id?: number | null }) => {
        const response = await publicApi.post<GenericDataResponse<BoxOfficeSession>>(`/box-offices/${boxOfficeShortId}/sessions`, payload);
        return response.data;
    },
    updateSession: async (boxOfficeShortId: IdParam, payload: { event_occurrence_id?: number; stripe_terminal_reader_id?: number | null }) => {
        const response = await publicApi.put<GenericDataResponse<BoxOfficeSession>>(`/box-offices/${boxOfficeShortId}/sessions/current`, payload);
        return response.data;
    },
    endSession: async (boxOfficeShortId: IdParam, token: string) => {
        await publicApi.delete(`/box-offices/${boxOfficeShortId}/sessions/current`, {headers: {[BOX_OFFICE_SESSION_HEADER]: token}});
    },
    getSeatMap: async (boxOfficeShortId: IdParam) => {
        const response = await publicApi.get<GenericDataResponse<PublicEventSeatMap>>(`/box-offices/${boxOfficeShortId}/seat-map`);
        return response.data;
    },
    getOccupiedSeats: async (boxOfficeShortId: IdParam) => {
        const response = await publicApi.get<GenericDataResponse<OccupiedSeat[]> & {meta?: {version?: number}}>(`/box-offices/${boxOfficeShortId}/occupied-seats`);
        return response.data;
    },
    getBestAvailableSeats: async (boxOfficeShortId: IdParam, items: {product_id: number; quantity: number}[], exclude: string[]) => {
        const response = await publicApi.post<GenericDataResponse<{seat_uids: string[][]}>>(
            `/box-offices/${boxOfficeShortId}/best-available-seats`,
            {items, exclude},
        );
        return response.data;
    },
    getProducts: async (boxOfficeShortId: IdParam) => {
        const response = await publicApi.get<GenericDataResponse<BoxOfficeCatalogue>>(`/box-offices/${boxOfficeShortId}/products`);
        return response.data;
    },
    createOrder: async (boxOfficeShortId: IdParam, payload: BoxOfficeOrderRequest) => {
        const response = await publicApi.post<GenericDataResponse<Order>>(`/box-offices/${boxOfficeShortId}/orders`, payload);
        return response.data;
    },
    tenderOrder: async (boxOfficeShortId: IdParam, orderShortId: IdParam, payload: BoxOfficeTenderRequest) => {
        const response = await publicApi.post<GenericDataResponse<Order>>(`/box-offices/${boxOfficeShortId}/orders/${orderShortId}/tender`, payload);
        return response.data;
    },
    getOrder: async (boxOfficeShortId: IdParam, orderShortId: IdParam) => {
        const response = await publicApi.get<GenericDataResponse<Order>>(`/box-offices/${boxOfficeShortId}/orders/${orderShortId}`);
        return response.data;
    },
    startCardPayment: async (boxOfficeShortId: IdParam, orderShortId: IdParam) => {
        const response = await publicApi.post<GenericDataResponse<Order>>(`/box-offices/${boxOfficeShortId}/orders/${orderShortId}/card`);
        return response.data;
    },
    cancelCardAction: async (boxOfficeShortId: IdParam, orderShortId: IdParam) => {
        const response = await publicApi.post<GenericDataResponse<Order>>(`/box-offices/${boxOfficeShortId}/orders/${orderShortId}/card/cancel-action`);
        return response.data;
    },
    abandonOrder: async (boxOfficeShortId: IdParam, orderShortId: IdParam) => {
        const response = await publicApi.post<GenericDataResponse<Order>>(`/box-offices/${boxOfficeShortId}/orders/${orderShortId}/abandon`);
        return response.data;
    },
    cancelOrder: async (boxOfficeShortId: IdParam, orderShortId: IdParam) => {
        const response = await publicApi.post<GenericDataResponse<Order>>(`/box-offices/${boxOfficeShortId}/orders/${orderShortId}/cancel`);
        return response.data;
    },
    resendConfirmation: async (boxOfficeShortId: IdParam, orderShortId: IdParam, email?: string) => {
        const response = await publicApi.post<GenericDataResponse<Order>>(`/box-offices/${boxOfficeShortId}/orders/${orderShortId}/resend-confirmation`, email ? {email} : {});
        return response.data;
    },
    getOrders: async (boxOfficeShortId: IdParam, pagination: QueryFilters) => {
        const response = await publicApi.get<GenericPaginatedResponse<Order>>(`/box-offices/${boxOfficeShortId}/orders` + queryParamsHelper.buildQueryString(pagination));
        return response.data;
    },
    checkInAttendees: async (checkInListShortId: IdParam, attendeePublicIds: string[]) => {
        const response = await publicApi.post<GenericDataResponse<PublicCheckIn[]>>(`/check-in-lists/${checkInListShortId}/check-ins`, {
            attendees: attendeePublicIds.map(public_id => ({public_id, action: 'check-in'})),
        });
        return response.data;
    },
};
