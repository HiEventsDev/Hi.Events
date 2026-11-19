import {CrmCampaignRequest} from "../../../api/crm.client.ts";
import {CrmCampaign, CrmMarketingChannel, CrmSegmentDefinition} from "../../../types.ts";
import {parseSegmentParam} from "../../../utilites/format.ts";
import {isEveryoneOnOneChannel} from "../../../utilites/labels.ts";

export interface CampaignFormValues {
    name: string;
    subject: string;
    preheader: string;
    body: string;
    audience: string;
    event_id: string | null;
    early_access_product_ids: string[];
}

const NAME_MAX_LENGTH = 150;

const nameFromSubject = (subject: string): string => Array.from(subject.trim()).slice(0, NAME_MAX_LENGTH).join('');

export const initialAudience = (segment: string | null): string => {
    const selection = parseSegmentParam(segment);

    return !segment || (selection.kind === 'preset' && isEveryoneOnOneChannel(selection.preset)) ? 'all' : segment;
};

export const CUSTOM_AUDIENCE = 'custom';

const audienceValue = (campaign: CrmCampaign): string => {
    if (campaign.definition) {
        return CUSTOM_AUDIENCE;
    }
    if (campaign.segment_id) {
        return `saved:${campaign.segment_id}`;
    }
    if (campaign.preset && !isEveryoneOnOneChannel(campaign.preset)) {
        return `preset:${campaign.preset}`;
    }
    return 'all';
};

export const campaignToFormValues = (campaign: CrmCampaign): CampaignFormValues => ({
    name: campaign.name === nameFromSubject(campaign.subject ?? '') ? '' : campaign.name,
    subject: campaign.subject ?? '',
    preheader: campaign.preheader ?? '',
    body: campaign.body ?? '',
    audience: audienceValue(campaign),
    event_id: campaign.event_id ? String(campaign.event_id) : null,
    early_access_product_ids: campaign.early_access_product_ids.map(String),
});

export const toRequest = (values: CampaignFormValues, customDefinition: CrmSegmentDefinition | null, templateId?: number, channel?: CrmMarketingChannel): CrmCampaignRequest => {
    const selection = parseSegmentParam(values.audience === 'all' ? null : values.audience);
    const isCustom = values.audience === CUSTOM_AUDIENCE && customDefinition !== null;

    return {
        name: values.name.trim() || nameFromSubject(values.subject),
        subject: values.subject.trim() || null,
        preheader: values.preheader.trim() || null,
        body: values.body || null,
        segment_id: selection.kind === 'saved' ? selection.segmentId : null,
        preset: selection.kind === 'preset' ? selection.preset : null,
        event_id: values.event_id ? Number(values.event_id) : null,
        early_access_product_ids: values.event_id ? values.early_access_product_ids.map(Number) : [],
        ...(isCustom ? {definition: customDefinition} : {}),
        ...(templateId ? {template_id: templateId} : {}),
        ...(channel ? {channel} : {}),
    };
};
