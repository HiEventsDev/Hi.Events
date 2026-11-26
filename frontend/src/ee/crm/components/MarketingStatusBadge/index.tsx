import {CrmEmailMarketingStatus} from "../../types.ts";
import {marketingStatusLabel} from "../../utilites/labels.ts";
import {StatusBadge, StatusTone} from "../../../../components/common/StatusBadge";

const TONES: Record<CrmEmailMarketingStatus, StatusTone> = {
    SUBSCRIBED: 'positive',
    NOT_SUBSCRIBED: 'neutral',
    UNSUBSCRIBED: 'waiting',
    BOUNCED: 'negative',
    COMPLAINED: 'negative',
};

export const MarketingStatusBadge = ({status}: { status: CrmEmailMarketingStatus }) => (
    <StatusBadge tone={TONES[status]}>{marketingStatusLabel(status)}</StatusBadge>
);
