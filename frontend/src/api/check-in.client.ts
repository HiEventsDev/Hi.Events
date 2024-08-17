import {publicApi} from "./public-client";
import {
    Attendee,
    CheckIn,
    CheckInList,
    GenericDataResponse,
    GenericPaginatedResponse,
    IdParam,
    QueryFilters,
} from "../types";
import {queryParamsHelper} from "../utilites/queryParamsHelper";

export const publicCheckInClient = {
    getCheckInList: async (checkInListShortId: IdParam) => {
        const response = await publicApi.get<GenericDataResponse<CheckInList>>(`/check-in-lists/${checkInListShortId}`);
        return response.data;
    },
    getCheckInListAttendees: async (checkInListShortId: IdParam, pagination: QueryFilters) => {
        const response = await publicApi.get<GenericPaginatedResponse<Attendee>>(`/check-in-lists/${checkInListShortId}/attendees` + queryParamsHelper.buildQueryString(pagination));
        return response.data;
    },
    createCheckIn: async (checkInListShortId: IdParam, attendeePublicId: IdParam) => {
        const response = await publicApi.post<GenericDataResponse<CheckIn[]>>(`/check-in-lists/${checkInListShortId}/check-ins`, {
            "attendee_public_ids": [attendeePublicId],
        });
        return response.data;
    },
    deleteCheckIn: async (checkInListShortId: IdParam, checkInShortId: IdParam) => {
        const response = await publicApi.delete<GenericDataResponse<CheckIn>>(`/check-in-lists/${checkInListShortId}/check-ins/${checkInShortId}`);
        return response.data;
    },
};
