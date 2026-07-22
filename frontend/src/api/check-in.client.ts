import {publicApi} from "./public-client";
import {
    Attendee,
    CheckInList,
    GenericDataResponse,
    GenericPaginatedResponse,
    IdParam, PublicCheckIn,
    QueryFilters,
} from "../types";
import {queryParamsHelper} from "../utilites/queryParamsHelper";

export const publicCheckInClient = {
    getCheckInList: async (checkInListShortId: IdParam, password?: string) => {
        const response = await publicApi.get<GenericDataResponse<CheckInList>>(`/check-in-lists/${checkInListShortId}`, {
            headers: password ? {'X-Check-In-List-Password': password} : {},
        });
        return response.data;
    },
    getCheckInListAttendees: async (checkInListShortId: IdParam, pagination: QueryFilters, password?: string) => {
        const response = await publicApi.get<GenericPaginatedResponse<Attendee>>(`/check-in-lists/${checkInListShortId}/attendees` + queryParamsHelper.buildQueryString(pagination), {
            headers: password ? {'X-Check-In-List-Password': password} : {},
        });
        return response.data;
    },
    getCheckInListAttendee: async (checkInListShortId: IdParam, attendeePublicId: IdParam, password?: string) => {
        const response = await publicApi.get<GenericDataResponse<Attendee>>(`/check-in-lists/${checkInListShortId}/attendees/${attendeePublicId}`, {
            headers: password ? {'X-Check-In-List-Password': password} : {},
        });
        return response.data;
    },
    createCheckIn: async (checkInListShortId: IdParam, attendeePublicId: IdParam, action: 'check-in' | 'check-in-and-mark-order-as-paid', password?: string) => {
        const response = await publicApi.post<GenericDataResponse<PublicCheckIn[]>>(`/check-in-lists/${checkInListShortId}/check-ins`, {
            "attendees": [
                {
                    "public_id": attendeePublicId,
                    "action": action
                }
            ]
        }, {
            headers: password ? {'X-Check-In-List-Password': password} : {},
        });
        return response.data;
    },
    deleteCheckIn: async (checkInListShortId: IdParam, checkInShortId: IdParam, password?: string) => {
        const response = await publicApi.delete<GenericDataResponse<PublicCheckIn>>(`/check-in-lists/${checkInListShortId}/check-ins/${checkInShortId}`, {
            headers: password ? {'X-Check-In-List-Password': password} : {},
        });
        return response.data;
    },
};
