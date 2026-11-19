import {t} from "@lingui/macro";
import {CrmCampaign, CrmSegmentList, CrmSegmentOptions} from "../../../types.ts";
import {isEveryoneOnOneChannel, presetLabel} from "../../../utilites/labels.ts";
import {deletedSegmentOptions} from "../../../utilites/segmentOptions.ts";
import {CUSTOM_AUDIENCE} from "./campaignForm.ts";

export const audienceOptions = (campaign: CrmCampaign | undefined, segments: CrmSegmentList | undefined, audience: string, isSms = false) => [
    {
        group: t`Everyone`,
        items: [
            {value: 'all', label: isSms ? t`Everyone who opted in to texts` : t`Everyone who opted in`},
            ...(campaign?.definition ? [{value: CUSTOM_AUDIENCE, label: t`Custom rules`}] : []),
        ],
    },
    {
        group: t`Ready-made`,
        items: (segments?.presets ?? [])
            .filter(preset => !isEveryoneOnOneChannel(preset.key))
            .map(preset => ({value: `preset:${preset.key}`, label: presetLabel(preset.key)})),
    },
    ...(segments && segments.data.length > 0 ? [{
        group: t`Your segments`,
        items: segments.data.map(segment => ({value: `saved:${segment.id}`, label: segment.name})),
    }] : []),
    ...deletedSegmentOptions(audience, segments),
];

export const eventOptions = (campaign: CrmCampaign | undefined, options: CrmSegmentOptions | undefined, linkedEventId: string | null) => {
    const events = (options?.events ?? [])
        .filter(event => event.status === 'LIVE' || String(event.id) === linkedEventId)
        .map(event => ({value: String(event.id), label: event.title}));

    if (linkedEventId && !events.some(option => option.value === linkedEventId) && campaign?.event_title && String(campaign.event_id) === linkedEventId) {
        events.push({value: linkedEventId, label: campaign.event_title});
    }

    return events;
};
