import {api} from "../../../api/client";
import {publicApi} from "../../../api/public-client";
import {GenericDataResponse, GenericPaginatedResponse, IdParam, QueryFilters} from "../../../types";
import {queryParamsHelper} from "../../../utilites/queryParamsHelper.ts";
import {
    CrmAdminCampaign,
    CrmAudienceOverview,
    CrmAutomation,
    CrmAutomationPreview,
    CrmAutomationSettings,
    CrmAutomationTemplate,
    CrmAutomationTrigger,
    CrmBulkOperation,
    CrmBulkSelection,
    CrmCampaign,
    CrmCampaignList,
    CrmCampaignListFilters,
    CrmCampaignPreview,
    CrmCampaignRecipient,
    CrmCampaignSendCheck,
    CrmCampaignTemplate,
    CrmCampaignTemplates,
    CrmConsentEvent,
    CrmContact,
    CrmContactActivity,
    CrmContactImport,
    CrmContactNote,
    CrmEventAudience,
    CrmLeadTime,
    CrmMarketingChannel,
    CrmRecipientFilter,
    CrmRetention,
    CrmSegment,
    CrmSegmentCount,
    CrmSegmentDefinition,
    CrmSegmentList,
    CrmSegmentOptions,
    CrmSegmentOptionsParams,
    CrmSegmentReference,
    CrmSignupForm,
    CrmSignupFormPublic,
    CrmSignupFormStatus,
    CrmSmsConnection,
    CrmSmsPreview,
    CrmSmsCountry,
    CrmSmsSender,
    CrmSubscriptionConfirmation,
    CrmSuppressionImportPreview,
    CrmSuppressionLookupResult,
    CrmSuppressionSummary,
    CrmSyncStatus,
    CrmTag,
    CrmUnsubscribeStatus,
} from "../types.ts";

const base = (organizerId: IdParam) => `organizers/${organizerId}/crm`;

const segmentReferenceParams = (segment?: CrmSegmentReference): Record<string, string> => {
    if (segment?.segmentId) {
        return {segment_id: String(segment.segmentId)};
    }
    if (segment?.preset) {
        return {preset: segment.preset};
    }
    if (segment?.definition) {
        return {definition: JSON.stringify(segment.definition)};
    }
    return {};
};

const bulkSelectionBody = (selection: CrmBulkSelection): Record<string, unknown> => selection.mode === 'ids'
    ? {mode: 'ids', contact_ids: selection.contactIds}
    : {
        mode: 'filter',
        query: selection.query || null,
        ...(selection.segment.segmentId ? {segment_id: selection.segment.segmentId} : {}),
        ...(selection.segment.preset ? {preset: selection.segment.preset} : {}),
        ...(selection.segment.definition ? {definition: selection.segment.definition} : {}),
    };

export interface CrmSignupFormRequest {
    name: string;
    status: CrmSignupFormStatus;
    title: string;
    description: string | null;
    button_label: string;
    success_message: string | null;
    asks_for_name: boolean;
    privacy_url: string | null;
    tag_ids: number[];
    show_on_organizer_page: boolean;
}

export interface CrmSignupSubmission {
    email: string;
    first_name?: string | null;
    last_name?: string | null;
    locale: string;
    turnstile_token?: string | null;
    website?: string;
}

export interface CrmContactRequest {
    email?: string;
    first_name: string | null;
    last_name: string | null;
    restore_deleted?: boolean;
}

export interface CrmTagRequest {
    name: string;
    color: string | null;
}

export interface CrmSegmentRequest {
    name: string;
    description: string | null;
    definition: CrmSegmentDefinition;
}

export interface CrmCampaignRequest {
    name: string;
    channel?: CrmMarketingChannel;
    subject: string | null;
    preheader: string | null;
    body: string | null;
    segment_id: number | null;
    preset: string | null;
    event_id: number | null;
    early_access_product_ids: number[];
    definition?: CrmSegmentDefinition;
    template_id?: number;
}

export interface CrmSmsConnectionRequest {
    account_sid: string;
    auth_token: string | null;
    messaging_service_sid: string;
    enabled_countries: CrmSmsCountry[];
}

export interface CrmCampaignDraftRequest {
    subject: string | null;
    preheader: string | null;
    body: string | null;
    event_id: number | null;
    early_access_product_ids: number[];
    contact_id: number | null;
}

export interface CrmSmsPreviewRequest {
    body: string | null;
    event_id: number | null;
    early_access_product_ids: number[];
    campaign_id?: number;
}

export interface CrmCampaignTemplateRequest {
    name: string;
    subject: string | null;
    preheader: string | null;
    body: string;
}

export interface CrmAutomationDraft extends CrmAutomationSettings {
    trigger: CrmAutomationTrigger;
    subject: string;
    preheader?: string | null;
    body: string;
    segment_id: number | null;
    preset: string | null;
}

export interface CrmAutomationRequest extends CrmAutomationDraft {
    name: string;
}

export const crmClient = {
    syncStatus: async (organizerId: IdParam) => {
        const response = await api.get<GenericDataResponse<CrmSyncStatus>>(`${base(organizerId)}/sync`);
        return response.data;
    },
    startSync: async (organizerId: IdParam) => {
        const response = await api.post<GenericDataResponse<CrmSyncStatus>>(`${base(organizerId)}/sync`);
        return response.data;
    },

    contacts: async (organizerId: IdParam, pagination: QueryFilters, segment?: CrmSegmentReference) => {
        const response = await api.get<GenericPaginatedResponse<CrmContact>>(
            `${base(organizerId)}/contacts` + queryParamsHelper.buildQueryString(pagination),
            {params: segmentReferenceParams(segment)},
        );
        return response.data;
    },
    suppressedContacts: async (organizerId: IdParam, pagination: QueryFilters, segment: CrmSegmentReference) => {
        const response = await api.get<GenericPaginatedResponse<CrmContact>>(
            `${base(organizerId)}/contacts` + queryParamsHelper.buildQueryString(pagination),
            {params: {...segmentReferenceParams(segment), with_suppression: '1'}},
        );
        return response.data;
    },
    suppressionSummary: async (organizerId: IdParam) => {
        const response = await api.get<GenericDataResponse<CrmSuppressionSummary>>(`${base(organizerId)}/suppressions/summary`);
        return response.data;
    },
    lookupSuppressions: async (organizerId: IdParam, emails: string[]) => {
        const response = await api.post<GenericDataResponse<CrmSuppressionLookupResult[]>>(`${base(organizerId)}/suppressions/lookup`, {emails});
        return response.data;
    },
    clearBounce: async (organizerId: IdParam, contactId: IdParam, contactEmailId: IdParam) => {
        const response = await api.post<GenericDataResponse<CrmContact>>(`${base(organizerId)}/contacts/${contactId}/emails/${contactEmailId}/clear-bounce`);
        return response.data;
    },
    previewSuppressionImport: async (organizerId: IdParam, file: File) => {
        const formData = new FormData();
        formData.append('file', file);
        const response = await api.post<GenericDataResponse<CrmSuppressionImportPreview>>(`${base(organizerId)}/imports/suppressions/preview`, formData, {
            headers: {'Content-Type': 'multipart/form-data'},
        });
        return response.data;
    },
    importSuppressions: async (organizerId: IdParam, file: File) => {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('kind', 'SUPPRESSIONS');
        const response = await api.post<GenericDataResponse<CrmContactImport>>(`${base(organizerId)}/imports`, formData, {
            headers: {'Content-Type': 'multipart/form-data'},
        });
        return response.data;
    },
    contact: async (organizerId: IdParam, contactId: IdParam) => {
        const response = await api.get<GenericDataResponse<CrmContact>>(`${base(organizerId)}/contacts/${contactId}`);
        return response.data;
    },
    createContact: async (organizerId: IdParam, contact: CrmContactRequest) => {
        const response = await api.post<GenericDataResponse<CrmContact>>(`${base(organizerId)}/contacts`, contact);
        return response.data;
    },
    updateContact: async (organizerId: IdParam, contactId: IdParam, contact: CrmContactRequest) => {
        const response = await api.put<GenericDataResponse<CrmContact>>(`${base(organizerId)}/contacts/${contactId}`, contact);
        return response.data;
    },
    deleteContact: async (organizerId: IdParam, contactId: IdParam) => {
        await api.delete(`${base(organizerId)}/contacts/${contactId}`);
    },
    mergeContacts: async (organizerId: IdParam, contactId: IdParam, sourceContactId: IdParam) => {
        const response = await api.post<GenericDataResponse<CrmContact>>(
            `${base(organizerId)}/contacts/${contactId}/merge`,
            {source_contact_id: sourceContactId},
        );
        return response.data;
    },
    splitContactEmail: async (organizerId: IdParam, contactId: IdParam, contactEmailId: IdParam) => {
        const response = await api.post<GenericDataResponse<CrmContact>>(
            `${base(organizerId)}/contacts/${contactId}/emails/${contactEmailId}/split`,
        );
        return response.data;
    },
    setPrimaryContactEmail: async (organizerId: IdParam, contactId: IdParam, contactEmailId: IdParam) => {
        const response = await api.post<GenericDataResponse<CrmContact>>(
            `${base(organizerId)}/contacts/${contactId}/emails/${contactEmailId}/primary`,
        );
        return response.data;
    },
    setContactTags: async (organizerId: IdParam, contactId: IdParam, tagIds: number[]) => {
        const response = await api.put<GenericDataResponse<CrmContact>>(
            `${base(organizerId)}/contacts/${contactId}/tags`,
            {tag_ids: tagIds},
        );
        return response.data;
    },
    unsubscribeContact: async (organizerId: IdParam, contactId: IdParam, channel: CrmMarketingChannel = 'EMAIL') => {
        const response = await api.post<GenericDataResponse<CrmContact>>(`${base(organizerId)}/contacts/${contactId}/unsubscribe`, {channel});
        return response.data;
    },
    contactActivities: async (organizerId: IdParam, contactId: IdParam, page: number) => {
        const response = await api.get<GenericPaginatedResponse<CrmContactActivity>>(
            `${base(organizerId)}/contacts/${contactId}/activities`,
            {params: {page, per_page: 25}},
        );
        return response.data;
    },
    contactConsent: async (organizerId: IdParam, contactId: IdParam, page: number) => {
        const response = await api.get<GenericPaginatedResponse<CrmConsentEvent>>(
            `${base(organizerId)}/contacts/${contactId}/consent`,
            {params: {page, per_page: 25}},
        );
        return response.data;
    },

    exportContacts: async (organizerId: IdParam, pagination: QueryFilters, segment?: CrmSegmentReference) => {
        const response = await api.get<Blob>(
            `${base(organizerId)}/contacts/export` + queryParamsHelper.buildQueryString(pagination),
            {params: segmentReferenceParams(segment), responseType: 'blob'},
        );
        return response.data;
    },
    exportSelectedContacts: async (organizerId: IdParam, contactIds: number[]) => {
        const response = await api.post<Blob>(`${base(organizerId)}/contacts/export`, {contact_ids: contactIds}, {responseType: 'blob'});
        return response.data;
    },
    bulkTagContacts: async (organizerId: IdParam, selection: CrmBulkSelection, action: 'ADD_TAGS' | 'REMOVE_TAGS', tagIds: number[]) => {
        const response = await api.post<GenericDataResponse<CrmBulkOperation>>(`${base(organizerId)}/contacts/bulk/tags`, {
            ...bulkSelectionBody(selection),
            action,
            tag_ids: tagIds,
        });
        return response.data;
    },
    bulkUnsubscribeContacts: async (organizerId: IdParam, selection: CrmBulkSelection, confirmationCount: number) => {
        const response = await api.post<GenericDataResponse<CrmBulkOperation>>(`${base(organizerId)}/contacts/bulk/unsubscribe`, {
            ...bulkSelectionBody(selection),
            confirmation_count: confirmationCount,
        });
        return response.data;
    },
    bulkDeleteContacts: async (organizerId: IdParam, selection: CrmBulkSelection, confirmationCount: number) => {
        const response = await api.post<GenericDataResponse<CrmBulkOperation>>(`${base(organizerId)}/contacts/bulk/delete`, {
            ...bulkSelectionBody(selection),
            confirmation_count: confirmationCount,
        });
        return response.data;
    },
    bulkOperation: async (organizerId: IdParam, operationId: IdParam) => {
        const response = await api.get<GenericDataResponse<CrmBulkOperation>>(`${base(organizerId)}/bulk-operations/${operationId}`);
        return response.data;
    },
    undoBulkOperation: async (organizerId: IdParam, operationId: IdParam) => {
        const response = await api.post<GenericDataResponse<CrmBulkOperation>>(`${base(organizerId)}/bulk-operations/${operationId}/undo`);
        return response.data;
    },
    signupForms: async (organizerId: IdParam) => {
        const response = await api.get<GenericDataResponse<CrmSignupForm[]>>(`${base(organizerId)}/forms`);
        return response.data;
    },
    createSignupForm: async (organizerId: IdParam, form: CrmSignupFormRequest) => {
        const response = await api.post<GenericDataResponse<CrmSignupForm>>(`${base(organizerId)}/forms`, form);
        return response.data;
    },
    updateSignupForm: async (organizerId: IdParam, formId: IdParam, form: CrmSignupFormRequest) => {
        const response = await api.put<GenericDataResponse<CrmSignupForm>>(`${base(organizerId)}/forms/${formId}`, form);
        return response.data;
    },
    deleteSignupForm: async (organizerId: IdParam, formId: IdParam) => {
        await api.delete(`${base(organizerId)}/forms/${formId}`);
    },
    importContacts: async (organizerId: IdParam, file: File, consentAttested: boolean, tagName: string) => {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('consent_attested', consentAttested ? '1' : '0');
        if (tagName.trim()) {
            formData.append('tag_name', tagName.trim());
        }
        const response = await api.post<GenericDataResponse<CrmContactImport>>(`${base(organizerId)}/imports`, formData, {
            headers: {'Content-Type': 'multipart/form-data'},
        });
        return response.data;
    },
    imports: async (organizerId: IdParam) => {
        const response = await api.get<GenericDataResponse<CrmContactImport[]>>(`${base(organizerId)}/imports`);
        return response.data;
    },

    contactNotes: async (organizerId: IdParam, contactId: IdParam) => {
        const response = await api.get<GenericDataResponse<CrmContactNote[]>>(`${base(organizerId)}/contacts/${contactId}/notes`);
        return response.data;
    },
    createContactNote: async (organizerId: IdParam, contactId: IdParam, note: string) => {
        const response = await api.post<GenericDataResponse<CrmContactNote>>(`${base(organizerId)}/contacts/${contactId}/notes`, {note});
        return response.data;
    },
    deleteContactNote: async (organizerId: IdParam, contactId: IdParam, noteId: IdParam) => {
        await api.delete(`${base(organizerId)}/contacts/${contactId}/notes/${noteId}`);
    },

    tags: async (organizerId: IdParam) => {
        const response = await api.get<GenericDataResponse<CrmTag[]>>(`${base(organizerId)}/tags`);
        return response.data;
    },
    createTag: async (organizerId: IdParam, tag: CrmTagRequest) => {
        const response = await api.post<GenericDataResponse<CrmTag>>(`${base(organizerId)}/tags`, tag);
        return response.data;
    },
    updateTag: async (organizerId: IdParam, tagId: IdParam, tag: CrmTagRequest) => {
        const response = await api.put<GenericDataResponse<CrmTag>>(`${base(organizerId)}/tags/${tagId}`, tag);
        return response.data;
    },
    deleteTag: async (organizerId: IdParam, tagId: IdParam) => {
        await api.delete(`${base(organizerId)}/tags/${tagId}`);
    },

    segments: async (organizerId: IdParam) => {
        const response = await api.get<CrmSegmentList>(`${base(organizerId)}/segments`);
        return response.data;
    },
    segmentCount: async (organizerId: IdParam, segment: CrmSegmentReference) => {
        const response = await api.get<GenericDataResponse<CrmSegmentCount>>(
            `${base(organizerId)}/segments/count`,
            {params: segmentReferenceParams(segment)},
        );
        return response.data;
    },
    segmentOptions: async (organizerId: IdParam, params: CrmSegmentOptionsParams) => {
        const response = await api.get<GenericDataResponse<CrmSegmentOptions>>(
            `${base(organizerId)}/segment-options`,
            {params: {...params, with_products: params.with_products ? 1 : undefined}},
        );
        return response.data;
    },
    createSegment: async (organizerId: IdParam, segment: CrmSegmentRequest) => {
        const response = await api.post<GenericDataResponse<CrmSegment>>(`${base(organizerId)}/segments`, segment);
        return response.data;
    },
    updateSegment: async (organizerId: IdParam, segmentId: IdParam, segment: CrmSegmentRequest) => {
        const response = await api.put<GenericDataResponse<CrmSegment>>(`${base(organizerId)}/segments/${segmentId}`, segment);
        return response.data;
    },
    deleteSegment: async (organizerId: IdParam, segmentId: IdParam) => {
        await api.delete(`${base(organizerId)}/segments/${segmentId}`);
    },

    overview: async (organizerId: IdParam) => {
        const response = await api.get<GenericDataResponse<CrmAudienceOverview>>(`${base(organizerId)}/insights/overview`);
        return response.data;
    },
    retention: async (organizerId: IdParam) => {
        const response = await api.get<GenericDataResponse<CrmRetention>>(`${base(organizerId)}/insights/retention`);
        return response.data;
    },
    leadTime: async (organizerId: IdParam) => {
        const response = await api.get<GenericDataResponse<CrmLeadTime>>(`${base(organizerId)}/insights/lead-time`);
        return response.data;
    },
    eventAudience: async (organizerId: IdParam, eventId: IdParam) => {
        const response = await api.get<GenericDataResponse<CrmEventAudience>>(`${base(organizerId)}/insights/events/${eventId}`);
        return response.data;
    },

    campaigns: async (organizerId: IdParam, filters: CrmCampaignListFilters, page: number) => {
        const response = await api.get<CrmCampaignList>(
            `${base(organizerId)}/campaigns`,
            {params: {...filters, query: filters.query || undefined, page, per_page: 20}},
        );
        return response.data;
    },
    campaignRecipients: async (organizerId: IdParam, campaignId: IdParam, filter: CrmRecipientFilter, page: number) => {
        const response = await api.get<GenericPaginatedResponse<CrmCampaignRecipient>>(
            `${base(organizerId)}/campaigns/${campaignId}/recipients`,
            {params: {filter: filter === 'all' ? undefined : filter, page}},
        );
        return response.data;
    },
    previewCampaignDraft: async (organizerId: IdParam, draft: CrmCampaignDraftRequest) => {
        const response = await api.post<GenericDataResponse<CrmCampaignPreview>>(`${base(organizerId)}/campaigns/preview`, draft);
        return response.data;
    },
    previewSmsCampaign: async (organizerId: IdParam, draft: CrmSmsPreviewRequest) => {
        const response = await api.post<GenericDataResponse<CrmSmsPreview>>(`${base(organizerId)}/campaigns/sms-preview`, draft);
        return response.data;
    },
    endEarlyAccess: async (organizerId: IdParam, campaignId: IdParam) => {
        const response = await api.post<GenericDataResponse<CrmCampaign>>(`${base(organizerId)}/campaigns/${campaignId}/end-early-access`);
        return response.data;
    },
    duplicateCampaign: async (organizerId: IdParam, campaignId: IdParam) => {
        const response = await api.post<GenericDataResponse<CrmCampaign> & {warnings: string[]}>(`${base(organizerId)}/campaigns/${campaignId}/duplicate`);
        return response.data;
    },
    campaignTemplates: async (organizerId: IdParam) => {
        const response = await api.get<CrmCampaignTemplates>(`${base(organizerId)}/templates`);
        return response.data;
    },
    createCampaignTemplate: async (organizerId: IdParam, template: CrmCampaignTemplateRequest) => {
        const response = await api.post<GenericDataResponse<CrmCampaignTemplate>>(`${base(organizerId)}/templates`, template);
        return response.data;
    },
    updateCampaignTemplate: async (organizerId: IdParam, templateId: IdParam, template: CrmCampaignTemplateRequest) => {
        const response = await api.put<GenericDataResponse<CrmCampaignTemplate>>(`${base(organizerId)}/templates/${templateId}`, template);
        return response.data;
    },
    deleteCampaignTemplate: async (organizerId: IdParam, templateId: IdParam) => {
        await api.delete(`${base(organizerId)}/templates/${templateId}`);
    },
    automations: async (organizerId: IdParam) => {
        const response = await api.get<GenericDataResponse<CrmAutomation[]>>(`${base(organizerId)}/automations`);
        return response.data;
    },
    automationTemplates: async (organizerId: IdParam) => {
        const response = await api.get<GenericDataResponse<CrmAutomationTemplate[]>>(`${base(organizerId)}/automations/templates`);
        return response.data;
    },
    previewAutomation: async (organizerId: IdParam, draft: CrmAutomationDraft, automationId: number | null) => {
        const response = await api.post<GenericDataResponse<CrmAutomationPreview>>(`${base(organizerId)}/automations/preview`, {...draft, automation_id: automationId});
        return response.data;
    },
    sendTestAutomation: async (organizerId: IdParam, draft: CrmAutomationDraft) => {
        const response = await api.post<{ message: string }>(`${base(organizerId)}/automations/test`, draft);
        return response.data;
    },
    createAutomation: async (organizerId: IdParam, automation: CrmAutomationRequest) => {
        const response = await api.post<GenericDataResponse<CrmAutomation>>(`${base(organizerId)}/automations`, automation);
        return response.data;
    },
    updateAutomation: async (organizerId: IdParam, automationId: IdParam, automation: CrmAutomationRequest) => {
        const {trigger: _trigger, ...body} = automation;
        const response = await api.put<GenericDataResponse<CrmAutomation>>(`${base(organizerId)}/automations/${automationId}`, body);
        return response.data;
    },
    setAutomationEnabled: async (organizerId: IdParam, automationId: IdParam, isEnabled: boolean) => {
        const response = await api.post<GenericDataResponse<CrmAutomation>>(`${base(organizerId)}/automations/${automationId}/${isEnabled ? 'enable' : 'disable'}`);
        return response.data;
    },
    deleteAutomation: async (organizerId: IdParam, automationId: IdParam) => {
        await api.delete(`${base(organizerId)}/automations/${automationId}`);
    },
    campaign: async (organizerId: IdParam, campaignId: IdParam) => {
        const response = await api.get<GenericDataResponse<CrmCampaign>>(`${base(organizerId)}/campaigns/${campaignId}`);
        return response.data;
    },
    createCampaign: async (organizerId: IdParam, campaign: CrmCampaignRequest) => {
        const response = await api.post<GenericDataResponse<CrmCampaign>>(`${base(organizerId)}/campaigns`, campaign);
        return response.data;
    },
    updateCampaign: async (organizerId: IdParam, campaignId: IdParam, campaign: CrmCampaignRequest) => {
        const response = await api.put<GenericDataResponse<CrmCampaign>>(`${base(organizerId)}/campaigns/${campaignId}`, campaign);
        return response.data;
    },
    deleteCampaign: async (organizerId: IdParam, campaignId: IdParam) => {
        await api.delete(`${base(organizerId)}/campaigns/${campaignId}`);
    },
    smsCampaignPreview: async (organizerId: IdParam, campaignId: IdParam) => {
        const response = await api.get<GenericDataResponse<CrmSmsPreview>>(`${base(organizerId)}/campaigns/${campaignId}/preview`);
        return response.data;
    },
    campaignPreview: async (organizerId: IdParam, campaignId: IdParam) => {
        const response = await api.get<GenericDataResponse<CrmCampaignPreview>>(`${base(organizerId)}/campaigns/${campaignId}/preview`);
        return response.data;
    },
    campaignSendCheck: async (organizerId: IdParam, campaignId: IdParam, isScheduled: boolean) => {
        const response = await api.get<GenericDataResponse<CrmCampaignSendCheck>>(`${base(organizerId)}/campaigns/${campaignId}/send-check`, {params: isScheduled ? {scheduled: 1} : {}});
        return response.data;
    },
    sendCampaign: async (organizerId: IdParam, campaignId: IdParam, scheduledAt: string | null) => {
        const response = await api.post<GenericDataResponse<CrmCampaign>>(
            `${base(organizerId)}/campaigns/${campaignId}/send`,
            {scheduled_at: scheduledAt},
        );
        return response.data;
    },
    cancelCampaign: async (organizerId: IdParam, campaignId: IdParam) => {
        const response = await api.post<GenericDataResponse<CrmCampaign>>(`${base(organizerId)}/campaigns/${campaignId}/cancel`);
        return response.data;
    },
    sendTestCampaign: async (organizerId: IdParam, campaignId: IdParam, phone?: string) => {
        const response = await api.post<{ message: string }>(`${base(organizerId)}/campaigns/${campaignId}/test`, phone ? {phone} : {});
        return response.data;
    },
    smsConnection: async (organizerId: IdParam) => {
        const response = await api.get<GenericDataResponse<CrmSmsConnection | null>>(`${base(organizerId)}/sms-connection`);
        return response.data;
    },
    smsSenders: async (organizerId: IdParam, accountSid: string, authToken: string | null) => {
        const response = await api.post<GenericDataResponse<CrmSmsSender[]>>(`${base(organizerId)}/sms-connection/senders`, {
            account_sid: accountSid,
            auth_token: authToken,
        });
        return response.data;
    },
    saveSmsConnection: async (organizerId: IdParam, connection: CrmSmsConnectionRequest) => {
        const response = await api.put<GenericDataResponse<CrmSmsConnection>>(`${base(organizerId)}/sms-connection`, connection);
        return response.data;
    },
    sendSmsTest: async (organizerId: IdParam, phone: string) => {
        await api.post(`${base(organizerId)}/sms-connection/test`, {phone});
    },
    deleteSmsConnection: async (organizerId: IdParam) => {
        await api.delete(`${base(organizerId)}/sms-connection`);
    },

    signupFormPublic: async (publicId: string) => {
        const response = await publicApi.get<GenericDataResponse<CrmSignupFormPublic>>(`crm/forms/${publicId}`);
        return response.data;
    },
    organizerSignupForm: async (organizerId: IdParam) => {
        const response = await publicApi.get<GenericDataResponse<CrmSignupFormPublic | null>>(`organizers/${organizerId}/signup-form`);
        return response.data;
    },
    submitSignupForm: async (publicId: string, submission: CrmSignupSubmission) => {
        const response = await publicApi.post<{ status: 'check_inbox' }>(`crm/forms/${publicId}/submissions`, submission);
        return response.data;
    },
    subscriptionConfirmation: async (token: string) => {
        const response = await publicApi.get<GenericDataResponse<CrmSubscriptionConfirmation>>(`crm/confirm/${token}`);
        return response.data;
    },
    confirmSubscription: async (token: string) => {
        const response = await publicApi.post<GenericDataResponse<CrmSubscriptionConfirmation>>(`crm/confirm/${token}`);
        return response.data;
    },
    unsubscribeStatus: async (token: string) => {
        const response = await publicApi.get<GenericDataResponse<CrmUnsubscribeStatus>>(`crm/unsubscribe/${token}`);
        return response.data;
    },
    unsubscribe: async (token: string) => {
        const response = await publicApi.post<GenericDataResponse<CrmUnsubscribeStatus>>(`crm/unsubscribe/${token}`);
        return response.data;
    },
    resubscribe: async (token: string) => {
        const response = await publicApi.post<GenericDataResponse<CrmUnsubscribeStatus>>(`crm/unsubscribe/${token}/resubscribe`);
        return response.data;
    },

    adminPendingCampaigns: async () => {
        const response = await api.get<GenericDataResponse<CrmAdminCampaign[]>>('admin/crm/campaigns/pending-review');
        return response.data;
    },
    adminReviewCampaign: async (campaign: CrmAdminCampaign, decision: 'approve' | 'reject', note: string | null) => {
        const response = await api.post<{ message: string }>(
            `admin/crm/campaigns/${campaign.id}/${decision}`,
            decision === 'approve' ? {reviewed_version: campaign.updated_at} : {note},
        );
        return response.data;
    },
};
