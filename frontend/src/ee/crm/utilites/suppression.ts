import {t} from "@lingui/macro";
import {CrmSuppressionDetail, CrmSuppressionReason} from "../types.ts";

export const SUPPRESSION_REASONS: CrmSuppressionReason[] = ['UNSUBSCRIBED', 'BOUNCED', 'COMPLAINED'];

export const suppressionReasonLabel = (reason: CrmSuppressionReason): string => ({
    UNSUBSCRIBED: t`Unsubscribed`,
    BOUNCED: t`Bounced`,
    COMPLAINED: t`Marked as spam`,
})[reason];

export const suppressionSourceLabel = (detail: CrmSuppressionDetail): string => {
    const campaign = detail.campaign_name;
    const file = detail.import_file_name;
    const user = detail.user_name;

    if (detail.provider_rejected) {
        return t`Refused by the email provider`;
    }

    switch (detail.source_type) {
        case 'UNSUBSCRIBE_LINK':
            return campaign ? t`Unsubscribe link in "${campaign}"` : t`Unsubscribe link in a campaign`;
        case 'CAMPAIGN':
            return campaign ? t`From "${campaign}"` : t`From a campaign`;
        case 'IMPORT':
            return file ? t`Imported from ${file}` : t`Imported`;
        case 'MANUAL':
            return user ? t`By ${user}` : t`By a team member`;
        case 'CONTACT':
            return t`On the unsubscribe page`;
        default:
            return t`From their booking`;
    }
};
