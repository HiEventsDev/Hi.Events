import {api} from "./client";
import {
    GenericDataResponse, GenericPaginatedResponse, IdParam, DocumentTemplate, QueryFilters,
} from "../types";
import {queryParamsHelper} from "../utilites/queryParamsHelper.ts";

export const documentTemplateClient = {
    create: async (template: Partial<DocumentTemplate>) => {
        const response = await api.post<GenericDataResponse<DocumentTemplate>>(
            `document-templates`, template
        );
        return response.data;
    },
    update: async (templateId: IdParam, template: Partial<DocumentTemplate>) => {
        const response = await api.put<GenericDataResponse<DocumentTemplate>>(
            `document-templates/${templateId}`, template
        );
        return response.data;
    },
    all: async (pagination: QueryFilters) => {
        const response = await api.get<GenericPaginatedResponse<DocumentTemplate>>(
            `document-templates` + queryParamsHelper.buildQueryString(pagination),
        );
        return response.data;
    },
    findById: async (templateId: IdParam) => {
        const response = await api.get<GenericDataResponse<DocumentTemplate>>(
            `document-templates/${templateId}`
        );
        return response.data;
    },
    delete: async (templateId: IdParam) => {
        const response = await api.delete<GenericDataResponse<DocumentTemplate>>(
            `document-templates/${templateId}`
        );
        return response.data;
    },
};
