import {QueryClient, QueryKey} from "@tanstack/react-query";
import {IdParam, QueryFilters} from "../../../types.ts";
import {CrmCampaignListFilters, CrmRecipientFilter, CrmSegmentOptionsParams, CrmSegmentReference} from "../types.ts";

const root = (organizerId: IdParam | undefined) => ['crm', String(organizerId)] as const;

export const crmKeys = {
    sync: (organizerId: IdParam | undefined) => [...root(organizerId), 'sync'],
    insights: (organizerId: IdParam | undefined) => [...root(organizerId), 'insights'],
    overview: (organizerId: IdParam | undefined) => [...crmKeys.insights(organizerId), 'overview'],
    retention: (organizerId: IdParam | undefined) => [...crmKeys.insights(organizerId), 'retention'],
    leadTime: (organizerId: IdParam | undefined) => [...crmKeys.insights(organizerId), 'lead-time'],
    eventAudience: (organizerId: IdParam | undefined, eventId: IdParam | undefined) =>
        [...crmKeys.insights(organizerId), 'event', String(eventId)],
    contactLists: (organizerId: IdParam | undefined) => [...root(organizerId), 'contacts'],
    contactList: (organizerId: IdParam | undefined, pagination: QueryFilters, segment?: CrmSegmentReference) =>
        [...crmKeys.contactLists(organizerId), pagination, segment],
    suppressedList: (organizerId: IdParam | undefined, pagination: QueryFilters, segment: CrmSegmentReference) =>
        [...crmKeys.contactLists(organizerId), 'suppressed', pagination, segment],
    suppressionSummary: (organizerId: IdParam | undefined) => [...crmKeys.contactLists(organizerId), 'suppression-summary'],
    contacts: (organizerId: IdParam | undefined) => [...root(organizerId), 'contact'],
    contact: (organizerId: IdParam | undefined, contactId: IdParam | undefined) =>
        [...crmKeys.contacts(organizerId), String(contactId)],
    contactNotes: (organizerId: IdParam | undefined, contactId: IdParam | undefined) =>
        [...crmKeys.contact(organizerId, contactId), 'notes'],
    contactActivities: (organizerId: IdParam | undefined, contactId: IdParam | undefined) =>
        [...crmKeys.contact(organizerId, contactId), 'activities'],
    contactConsent: (organizerId: IdParam | undefined, contactId: IdParam | undefined) =>
        [...crmKeys.contact(organizerId, contactId), 'consent'],
    tags: (organizerId: IdParam | undefined) => [...root(organizerId), 'tags'],
    smsConnection: (organizerId: IdParam | undefined) => [...root(organizerId), 'sms-connection'],
    signupForms: (organizerId: IdParam | undefined) => [...root(organizerId), 'forms'],
    bulkOperation: (organizerId: IdParam | undefined, operationId: IdParam | undefined) =>
        [...root(organizerId), 'bulk-operation', String(operationId)],
    imports: (organizerId: IdParam | undefined) => [...root(organizerId), 'imports'],
    segments: (organizerId: IdParam | undefined) => [...root(organizerId), 'segments'],
    segmentCounts: (organizerId: IdParam | undefined) => [...root(organizerId), 'segment-count'],
    segmentCount: (organizerId: IdParam | undefined, segment: CrmSegmentReference) =>
        [...crmKeys.segmentCounts(organizerId), segment],
    segmentOptions: (organizerId: IdParam | undefined, params: CrmSegmentOptionsParams) =>
        [...root(organizerId), 'segment-options', params],
    campaignLists: (organizerId: IdParam | undefined) => [...root(organizerId), 'campaigns'],
    campaignList: (organizerId: IdParam | undefined, filters: CrmCampaignListFilters, page: number) =>
        [...crmKeys.campaignLists(organizerId), filters, page],
    campaign: (organizerId: IdParam | undefined, campaignId: IdParam | undefined) =>
        [...root(organizerId), 'campaign', String(campaignId)],
    campaignRecipients: (
        organizerId: IdParam | undefined,
        campaignId: IdParam | undefined,
        filter: CrmRecipientFilter,
        page: number,
    ) => [...crmKeys.campaign(organizerId, campaignId), 'recipients', filter, page],
    campaignSendCheck: (organizerId: IdParam | undefined, campaignId: IdParam | undefined, isScheduled: boolean) =>
        [...crmKeys.campaign(organizerId, campaignId), 'send-check', isScheduled],
    campaignPreview: (organizerId: IdParam | undefined, campaignId: IdParam | undefined) =>
        [...root(organizerId), 'campaign-preview', String(campaignId)],
    campaignDraftPreview: (organizerId: IdParam | undefined, draft: unknown) =>
        [...root(organizerId), 'campaign-draft-preview', draft],
    campaignSmsPreview: (organizerId: IdParam | undefined, draft: unknown) =>
        [...root(organizerId), 'campaign-sms-preview', draft],
    campaignTemplates: (organizerId: IdParam | undefined) => [...root(organizerId), 'campaign-templates'],
    automations: (organizerId: IdParam | undefined) => [...root(organizerId), 'automations'],
    automationTemplates: (organizerId: IdParam | undefined) => [...root(organizerId), 'automation-templates'],
    automationPreview: (organizerId: IdParam | undefined, draft: unknown) =>
        [...root(organizerId), 'automation-preview', draft],
};

interface CrmInvalidation {
    queryKey: QueryKey;
    exact?: boolean;
}

export const exactly = (queryKey: QueryKey): CrmInvalidation => ({queryKey, exact: true});

export const invalidateCrm = (queryClient: QueryClient, keys: (QueryKey | CrmInvalidation)[]) =>
    Promise.all(keys.map(key => queryClient.invalidateQueries('queryKey' in key ? key : {queryKey: key})));

export const audienceMembershipKeys = (organizerId: IdParam): QueryKey[] => [
    crmKeys.contactLists(organizerId),
    crmKeys.segments(organizerId),
    crmKeys.segmentCounts(organizerId),
    crmKeys.insights(organizerId),
    crmKeys.sync(organizerId),
];

export const crmPublicKeys = {
    signupForm: (publicId: string | undefined) => ['crm-public', 'signup-form', publicId],
    organizerSignupForm: (organizerId: IdParam | undefined) => ['crm-public', 'organizer-signup-form', String(organizerId)],
    subscriptionConfirmation: (token: string | undefined) => ['crm-public', 'subscription-confirmation', token],
};
