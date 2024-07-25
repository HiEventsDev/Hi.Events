import {api} from "./client";
import {
    CapacityAssignment,
    CapacityAssignmentRequest,
    GenericDataResponse,
    GenericPaginatedResponse,
    IdParam,
} from "../types";

export const capacityAssignmentClient = {
    create: async (eventId: IdParam, capacityAssignment: CapacityAssignmentRequest) => {
        const response = await api.post<GenericDataResponse<CapacityAssignment>>(`events/${eventId}/capacity-assignments`, capacityAssignment);
        return response.data;
    },
    update: async (eventId: IdParam, capacityAssignmentId: IdParam, capacityAssignment: CapacityAssignmentRequest) => {
        const response = await api.put<GenericDataResponse<CapacityAssignment>>(`events/${eventId}/capacity-assignments/${capacityAssignmentId}`, capacityAssignment);
        return response.data;
    },
    all: async (eventId: IdParam) => {
        const response = await api.get<GenericPaginatedResponse<CapacityAssignment>>(`events/${eventId}/capacity-assignments`);
        return response.data;
    },
    get: async (eventId: IdParam, capacityAssignmentId: IdParam) => {
        const response = await api.get<GenericDataResponse<CapacityAssignment>>(`events/${eventId}/capacity-assignments/${capacityAssignmentId}`);
        return response.data;
    },
    delete: async (eventId: IdParam, capacityAssignmentId: IdParam) => {
        const response = await api.delete<GenericDataResponse<CapacityAssignment>>(`events/${eventId}/capacity-assignments/${capacityAssignmentId}`);
        return response.data;
    },
}
