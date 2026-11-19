import {CrmCampaignStatus} from "../../types.ts";
import {campaignStatusLabel} from "../../utilites/labels.ts";
import {StatusBadge, StatusTone} from "../StatusBadge";

const TONES: Record<CrmCampaignStatus, StatusTone> = {
    DRAFT: 'neutral',
    SCHEDULED: 'active',
    PENDING_REVIEW: 'waiting',
    SENDING: 'active',
    SENT: 'positive',
    PAUSED: 'waiting',
    CANCELLED: 'neutral',
    REJECTED: 'negative',
};

export const CampaignStatusBadge = ({status, isStopped = false}: { status: CrmCampaignStatus, isStopped?: boolean }) => (
    <StatusBadge tone={TONES[status]}>{campaignStatusLabel(status, isStopped)}</StatusBadge>
);
