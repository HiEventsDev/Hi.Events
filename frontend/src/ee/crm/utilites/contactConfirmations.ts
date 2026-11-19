import {t} from "@lingui/macro";
import {CrmEmailMarketingStatus} from "../types.ts";

export const deleteContactsConsequence = (): string =>
    t`This removes their notes, tags and history from Audience. Their orders and tickets aren't affected, and they won't be added back from future orders.`;

const emailOptInRoutes = (): string =>
    t`Your team can't undo this. They're only subscribed again if they opt in themselves, at checkout or through a signup form.`;

export const unsubscribeContactConsequence = (status: CrmEmailMarketingStatus): string => {
    switch (status) {
        case 'NOT_SUBSCRIBED':
            return [
                t`This contact hasn't opted in, so they don't receive campaigns or automations now. This records that they don't want them and marks them as unsubscribed.`,
                emailOptInRoutes(),
            ].join(' ');
        case 'BOUNCED':
            return [
                t`This contact's address bounced, so they don't receive campaigns or automations now. This records that they don't want them: they stay unsubscribed if the address is reactivated.`,
                emailOptInRoutes(),
            ].join(' ');
        default:
            return [t`They'll stop receiving campaigns and automations.`, emailOptInRoutes()].join(' ');
    }
};

export const bulkUnsubscribeContactsConsequence = (): string => [
    t`They'll stop receiving campaigns and automations. Contacts who haven't opted in or whose address bounced are marked as unsubscribed too.`,
    emailOptInRoutes(),
].join(' ');

export const smsOptOutConsequence = (canOptInByReply: boolean): string => canOptInByReply
    ? t`They'll stop receiving marketing text messages. Your team can't undo this. They're only opted in again if they text START to your number or tick the text message box on a future order.`
    : t`They'll stop receiving marketing text messages. Your team can't undo this. They're only opted in again if they tick the text message box on a future order.`;
