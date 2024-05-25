import {api} from "./client";
import {
    GenericDataResponse,
    GenericPaginatedResponse,
    IdParam,
    Question,
    QuestionRequestData,
    SortableItem,
} from "../types";
import {publicApi} from "./public-client.ts";

export const questionClient = {
    create: async (eventId: IdParam, question: QuestionRequestData) => {
        const response = await api.post<GenericDataResponse<Question>>(`events/${eventId}/questions`, question);
        return response.data;
    },
    update: async (eventId: IdParam, questionId: IdParam, question: QuestionRequestData) => {
        const response = await api.put<GenericDataResponse<Question>>(`events/${eventId}/questions/${questionId}`, question);
        return response.data;
    },
    all: async (eventId: IdParam) => {
        const response = await api.get<GenericPaginatedResponse<Question>>(`events/${eventId}/questions`);
        return response.data;
    },
    get: async (eventId: IdParam, questionId: IdParam) => {
        const response = await api.get<GenericDataResponse<Question>>(`events/${eventId}/questions/${questionId}`);
        return response.data;
    },
    delete: async (eventId: IdParam, questionId: IdParam) => {
        const response = await api.delete<GenericDataResponse<Question>>(`events/${eventId}/questions/${questionId}`);
        return response.data;
    },
    sortQuestions: async (eventId: IdParam, questionsSort: SortableItem[]) => {
        return await api.post(`/events/${eventId}/questions/sort`, questionsSort);
    }
}

export const questionClientPublic = {
    all: async (eventId: IdParam) => {
        const response = await publicApi.get<GenericPaginatedResponse<Question>>(`events/${eventId}/questions`);
        return response.data;
    },
}
