import {ContactAttributeDefinition, GenericDataResponse, GenericPaginatedResponse, IdParam, QueryFilters} from "../types.ts";
import {api} from "./client.ts";
import {queryParamsHelper} from "../utilites/queryParamsHelper.ts";

export const contactAttributeDefinitionClient = {
    create: async (accountId: IdParam, payload: Partial<ContactAttributeDefinition>) => {
        const response = await api.post<GenericDataResponse<ContactAttributeDefinition>>(
            `accounts/${accountId}/contact-attribute-definitions`,
            payload,
        );
        return response.data;
    },
    all: async (accountId: IdParam) => {
        const response = await api.get<GenericPaginatedResponse<ContactAttributeDefinition>>(
            `accounts/${accountId}/contact-attribute-definitions`,
        );
        return response.data;
    },
    update: async (accountId: IdParam, definitionId: IdParam, payload: Partial<ContactAttributeDefinition>) => {
        const response = await api.put<GenericDataResponse<ContactAttributeDefinition>>(
            `accounts/${accountId}/contact-attribute-definitions/${definitionId}`,
            payload,
        );
        return response.data;
    },
    delete: async (accountId: IdParam, definitionId: IdParam) => {
        return await api.delete(`accounts/${accountId}/contact-attribute-definitions/${definitionId}`);
    },
    linkedQuestions: async (accountId: IdParam, definitionId: IdParam, params: QueryFilters = {}) => {
        const response = await api.get<GenericPaginatedResponse<LinkedQuestionRow>>(
            `accounts/${accountId}/contact-attribute-definitions/${definitionId}/linked-questions`
            + queryParamsHelper.buildQueryString(params),
        );
        return response.data;
    },
};

export interface LinkedQuestionRow {
    question_id: number;
    question_title: string;
    question_belongs_to: string;
    question_required: boolean;
    question_is_hidden: boolean;
    question_type: string;
    event_id: number;
    event_title: string;
    event_start_date: string | null;
    event_status: string | null;
}
