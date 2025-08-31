import {api} from "./client";
import {
    EmailTemplate,
    EmailTemplateToken,
    EmailTemplatePreview,
    CreateEmailTemplateRequest,
    UpdateEmailTemplateRequest,
    PreviewEmailTemplateRequest,
    GenericDataResponse,
    IdParam,
    EmailTemplateType,
    DefaultEmailTemplate
} from "../types";

export const emailTemplateClient = {
    // Organizer-level templates
    getByOrganizer: async (organizerId: IdParam, params?: { template_type?: EmailTemplateType, include_inactive?: boolean }) => {
        const queryParams = new URLSearchParams();
        if (params?.template_type) queryParams.append('template_type', params.template_type);
        if (params?.include_inactive) queryParams.append('include_inactive', 'true');
        
        const queryString = queryParams.toString();
        const url = `organizers/${organizerId}/email-templates${queryString ? `?${queryString}` : ''}`;
        
        const response = await api.get<GenericDataResponse<EmailTemplate[]>>(url);
        return response.data;
    },

    createForOrganizer: async (organizerId: IdParam, templateData: CreateEmailTemplateRequest) => {
        const response = await api.post<GenericDataResponse<EmailTemplate>>(`organizers/${organizerId}/email-templates`, templateData);
        return response.data;
    },

    updateForOrganizer: async (organizerId: IdParam, templateId: IdParam, templateData: UpdateEmailTemplateRequest) => {
        const response = await api.put<GenericDataResponse<EmailTemplate>>(`organizers/${organizerId}/email-templates/${templateId}`, templateData);
        return response.data;
    },

    deleteForOrganizer: async (organizerId: IdParam, templateId: IdParam) => {
        const response = await api.delete<{ message: string }>(`organizers/${organizerId}/email-templates/${templateId}`);
        return response.data;
    },

    previewForOrganizer: async (organizerId: IdParam, previewData: PreviewEmailTemplateRequest) => {
        const response = await api.post<EmailTemplatePreview>(`organizers/${organizerId}/email-templates/preview`, previewData);
        return response.data;
    },

    // Event-level templates
    getByEvent: async (eventId: IdParam, params?: { template_type?: EmailTemplateType, include_inactive?: boolean }) => {
        const queryParams = new URLSearchParams();
        if (params?.template_type) queryParams.append('template_type', params.template_type);
        if (params?.include_inactive) queryParams.append('include_inactive', 'true');
        
        const queryString = queryParams.toString();
        const url = `events/${eventId}/email-templates${queryString ? `?${queryString}` : ''}`;
        
        const response = await api.get<GenericDataResponse<EmailTemplate[]>>(url);
        return response.data;
    },

    createForEvent: async (eventId: IdParam, templateData: CreateEmailTemplateRequest) => {
        const response = await api.post<GenericDataResponse<EmailTemplate>>(`events/${eventId}/email-templates`, templateData);
        return response.data;
    },

    updateForEvent: async (eventId: IdParam, templateId: IdParam, templateData: UpdateEmailTemplateRequest) => {
        const response = await api.put<GenericDataResponse<EmailTemplate>>(`events/${eventId}/email-templates/${templateId}`, templateData);
        return response.data;
    },

    deleteForEvent: async (eventId: IdParam, templateId: IdParam) => {
        const response = await api.delete<{ message: string }>(`events/${eventId}/email-templates/${templateId}`);
        return response.data;
    },

    previewForEvent: async (eventId: IdParam, previewData: PreviewEmailTemplateRequest) => {
        const response = await api.post<EmailTemplatePreview>(`events/${eventId}/email-templates/preview`, previewData);
        return response.data;
    },

    // Get available tokens
    getAvailableTokens: async (templateType: EmailTemplateType) => {
        const response = await api.get<{ tokens: EmailTemplateToken[] }>(`email-templates/tokens/${templateType}`);
        return response.data;
    },

    // Get default templates
    getDefaultTemplates: async () => {
        const response = await api.get<Record<EmailTemplateType, DefaultEmailTemplate>>('email-templates/defaults');
        return response.data;
    },
};