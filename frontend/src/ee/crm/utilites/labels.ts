import {plural, t} from "@lingui/macro";
import {getConfig} from "../../../utilites/config.ts";
import {
    CrmActivityType,
    CrmAutomation,
    CrmAutomationTrigger,
    CrmCampaign,
    CrmCampaignStatus,
    CrmConsentAction,
    CrmContactSource,
    CrmEmailMarketingStatus,
    CrmSegmentFieldKey,
    CrmSegmentOperator,
    CrmSegmentPresetKey,
    CrmSmsMarketingStatus,
} from "../types.ts";

export const isEveryoneOnOneChannel = (preset: CrmSegmentPresetKey): boolean =>
    preset === 'subscribers' || preset === 'sms_subscribers';

export const presetLabel = (preset: CrmSegmentPresetKey): string => ({
    subscribers: t`Subscribers`,
    sms_subscribers: t`Text subscribers`,
    first_timers: t`First-timers`,
    repeat_buyers: t`Repeat buyers`,
    top_fans: t`Top fans`,
    lapsed: t`Lapsed regulars`,
    no_shows: t`Frequent no-shows`,
    missed_out: t`Missed out`,
    abandoned_checkout: t`Abandoned checkout`,
})[preset];

export const presetDescription = (preset: CrmSegmentPresetKey): string => ({
    subscribers: t`Opted in to hear from you by email`,
    sms_subscribers: t`Opted in to hear from you by text message`,
    first_timers: t`Booked their first event with you in the last 90 days`,
    repeat_buyers: t`Booked two or more of your events`,
    top_fans: t`Booked four or more of your events`,
    lapsed: t`Booked twice or more, but not in the last 6 months`,
    no_shows: t`Missed two or more event dates they had a ticket for. Only ended dates where someone checked in count.`,
    missed_out: t`Joined a waitlist and never got a ticket`,
    abandoned_checkout: t`Started checkout and never finished`,
})[preset];

export const marketingStatusLabel = (status: CrmEmailMarketingStatus): string => ({
    SUBSCRIBED: t`Subscribed`,
    NOT_SUBSCRIBED: t`Not subscribed`,
    UNSUBSCRIBED: t`Unsubscribed`,
    BOUNCED: t`Bounced`,
    COMPLAINED: t`Marked as spam`,
})[status];

export const marketingStatusDescription = (status: CrmEmailMarketingStatus): string => ({
    SUBSCRIBED: t`Opted in to marketing emails. You can include them in campaigns.`,
    NOT_SUBSCRIBED: t`Hasn't opted in to marketing emails, so campaigns skip them.`,
    UNSUBSCRIBED: t`Asked not to receive marketing emails. Campaigns skip them.`,
    BOUNCED: t`Their address rejected an email. Campaigns skip them.`,
    COMPLAINED: t`Reported one of your emails as spam. Campaigns skip them permanently.`,
})[status];

export const smsMarketingStatusDescription = (status: CrmSmsMarketingStatus): string => ({
    SUBSCRIBED: t`Opted in to marketing text messages on this number.`,
    NOT_SUBSCRIBED: t`Hasn't opted in to marketing text messages on this number.`,
    UNSUBSCRIBED: t`Asked not to receive marketing text messages on this number.`,
})[status];

export const smsConsentActionLabel = (action: CrmConsentAction): string => action === 'OPT_IN'
    ? t`Opted in to text messages`
    : t`Opted out of text messages`;

export const consentActionLabel = (action: CrmConsentAction): string => ({
    OPT_IN: t`Opted in`,
    OPT_OUT: t`Opted out`,
    BOUNCED: t`Bounced`,
    COMPLAINED: t`Marked as spam`,
    BOUNCE_CLEARED: t`Bounce cleared`,
})[action];

export const sourceLabel = (source: CrmContactSource): string => ({
    ORDER: t`Placed an order`,
    ATTENDEE: t`Named on a ticket`,
    WAITLIST: t`Joined a waitlist`,
    IMPORT: t`Imported`,
    MANUAL: t`Added manually`,
    FORM: t`Signed up with a form`,
})[source];

export const activityLabel = (type: CrmActivityType): string => ({
    ORDER_COMPLETED: t`Placed an order`,
    ORDER_AWAITING_PAYMENT: t`Order awaiting payment`,
    ORDER_CANCELLED: t`Order cancelled`,
    ORDER_REFUNDED: t`Refunded`,
    CHECKOUT_ABANDONED: t`Abandoned checkout`,
    TICKET_HELD: t`Ticket`,
    CHECKED_IN: t`Checked in`,
    WAITLIST_JOINED: t`Joined the waitlist`,
    CAMPAIGN_SENT: t`Sent a campaign`,
    IMPORTED: t`Imported`,
})[type];

export const segmentFieldLabel = (field: CrmSegmentFieldKey): string => ({
    orders_count: t`Number of orders`,
    tickets_count: t`Number of tickets`,
    events_count: t`Number of events booked`,
    attended_count: t`Number of check-ins`,
    no_show_count: t`Number of no-shows`,
    total_spent: t`Total spent`,
    last_order_at: t`Last order`,
    last_attended_at: t`Last check-in`,
    first_seen_at: t`First seen`,
    ticket_event: t`Had a ticket for`,
    checked_in_event: t`Checked in to`,
    ticket_product: t`Ticket type`,
    ticket_location: t`Had a ticket at venue`,
    tag: t`Tag`,
    email_marketing_status: t`Email status`,
    sms_marketing_status: t`Text message status`,
    has_phone: t`Has a mobile number`,
    source: t`How they were added`,
    used_promo_code: t`Used a promo code`,
    waitlist_missed_out: t`Missed out on a waitlist`,
    abandoned_checkout: t`Abandoned a checkout`,
})[field];

export const segmentOperatorLabel = (operator: CrmSegmentOperator): string => ({
    gte: t`is at least`,
    lte: t`is at most`,
    eq: t`is exactly`,
    within_days: t`in the last`,
    not_within_days: t`more than`,
    before: t`before`,
    after: t`after`,
    is_empty: t`never`,
    in: t`is any of`,
    not_in: t`is none of`,
    is_true: t`yes`,
    is_false: t`no`,
})[operator];

export const leadTimeBucketLabel = (bucket: number): string => [
    t`On the day`,
    t`1–2 days before`,
    t`3–6 days before`,
    t`1–2 weeks before`,
    t`2–4 weeks before`,
    t`1–2 months before`,
    t`2+ months before`,
][bucket] ?? '';

export const isCampaignStopped = (campaign: Pick<CrmCampaign, 'status' | 'sent_at'>): boolean =>
    campaign.status === 'CANCELLED' && !!campaign.sent_at;

export const canAttributeCampaignRevenue = (campaign: Pick<CrmCampaign, 'channel' | 'promo_code' | 'delivery_reports_available'>): boolean =>
    !!campaign.promo_code || (campaign.channel !== 'SMS' && campaign.delivery_reports_available !== false);

export const campaignStatusLabel = (status: CrmCampaignStatus, isStopped = false): string => ({
    DRAFT: t`Draft`,
    SCHEDULED: t`Scheduled`,
    PENDING_REVIEW: t`Waiting for review`,
    SENDING: t`Sending`,
    SENT: t`Sent`,
    PAUSED: t`Paused`,
    CANCELLED: isStopped ? t`Stopped` : t`Cancelled`,
    REJECTED: t`Not approved`,
})[status];

export const campaignStatusReason = (
    reason: string | null,
    isAutomated = false,
    params: CrmCampaign['status_reason_params'] = null,
): string | null => {
    switch (reason) {
        case null:
            return null;
        case 'FIRST_CAMPAIGN':
            return t`We check every account's first campaign before it goes out.`;
        case 'UNTRUSTED_ACCOUNT':
            return t`Campaigns from new accounts are checked before they go out.`;
        case 'RECENT_IMPORT':
            return t`Some recipients only opted in through a recent import, so we check this campaign before it goes out.`;
        case 'REJECTED_BY_REVIEW': {
            const supportEmail = getConfig('VITE_PLATFORM_SUPPORT_EMAIL');
            if (supportEmail) {
                return isAutomated
                    ? t`This automated email wasn't approved to send, so nobody received it. Check the automation's email, or contact us at ${supportEmail} if you think that's a mistake.`
                    : t`This campaign wasn't approved to send. Edit it and try again, or contact us at ${supportEmail} if you think that's a mistake.`;
            }
            return isAutomated
                ? t`This automated email wasn't approved to send, so nobody received it. Check the automation's email, or contact us if you think that's a mistake.`
                : t`This campaign wasn't approved to send. Edit it and try again, or contact us if you think that's a mistake.`;
        }
        case 'COMPLAINT_RATE':
            return t`We paused this campaign because too many recipients marked it as spam. It won't start again, and nobody else will get it.`;
        case 'BOUNCE_RATE':
            return t`We paused this campaign because too many addresses rejected it. It won't start again, and nobody else will get it.`;
        case 'SMS_CONNECTION_REFUSED':
            return t`We paused this campaign because Twilio stopped accepting your saved account details or Messaging Service. It won't start again, and nobody else will get it. Check your Twilio connection before sending another.`;
        case 'SMS_DISCONNECTED':
            return t`We paused this campaign because Twilio was disconnected while it was sending. It won't start again, and nobody else will get it.`;
        case 'AUTOMATION_SKIPPED':
            return t`Nobody this automation was meant for had opted in to marketing emails, so nothing was sent.`;
        case 'ORGANIZER_DELETED':
            return t`This campaign stopped because its organizer was deleted.`;
        case 'SCHEDULED_SEND_EXPIRED': {
            const hours = Number(params?.hours ?? 0);
            return hours > 0
                ? plural(hours, {
                    one: `This campaign couldn't be sent within # hour of its scheduled time, so it's back in your drafts and nobody received it. Schedule it again if you still want to send it.`,
                    other: `This campaign couldn't be sent within # hours of its scheduled time, so it's back in your drafts and nobody received it. Schedule it again if you still want to send it.`,
                })
                : t`This campaign couldn't be sent in time after its scheduled time, so it's back in your drafts and nobody received it. Schedule it again if you still want to send it.`;
        }
        case 'SCHEDULED_SEND_FAILED':
            return t`This campaign couldn't be sent at its scheduled time, so it's back in your drafts and nobody received it. Press Send to see what needs fixing.`;
        case 'NO_RECIPIENTS':
            return t`Nobody you chose could receive this campaign at its scheduled time, so it's back in your drafts. Choose who to send it to and try again.`;
        case 'REVIEW_EXPIRED':
            return t`This automated email waited too long for review, so it was cancelled and nobody received it.`;
        case 'AUTOMATION_STOPPED':
            return t`This automated email was stopped because its automation was switched off or deleted. Nobody else will get it.`;
        default:
            return t`This campaign didn't go out as planned. If it's in your drafts, press Send to see what needs fixing.`;
    }
};

export const recipientFailureReason = (reason: string | null, isSms: boolean): string => {
    switch (reason) {
        case 'UNSUBSCRIBED':
            return isSms ? t`Opted out of text messages before it reached them` : t`Unsubscribed before it reached them`;
        case 'NO_CONSENT':
            return isSms ? t`No longer opted in to text messages, or their number changed` : t`No longer opted in to marketing emails`;
        case 'EMAIL_CHANGED':
            return t`Their email address changed first`;
        case 'BOUNCED':
            return t`Bounced or marked as spam before it reached them`;
        case 'PROVIDER_SUPPRESSED':
            return isSms ? t`Twilio lists this number as opted out` : t`The mail service lists this address as inactive`;
        case 'PROVIDER_UNAVAILABLE':
            return isSms ? t`Twilio couldn't take the text` : t`The mail service couldn't take the email`;
        case 'TIMEOUT':
            return isSms
                ? t`Twilio didn't answer in time. Not retried, in case the text did go out.`
                : t`The mail service didn't confirm it took the email. Not retried, in case the email did go out.`;
        case 'UNDELIVERED':
            return t`Twilio accepted the text, but the mobile network couldn't deliver it. The number may be a landline, switched off or blocked by the network.`;
        case 'PROVIDER_REJECTED':
            return isSms
                ? t`Twilio refused this number. It may not be a mobile, or your sender can't reach it.`
                : t`The mail service refused this email`;
        case 'SEND_ERROR':
            return t`Something went wrong before this could be sent`;
        case 'NO_PHONE':
            return t`Their mobile number changed or was removed first`;
        case 'COUNTRY_NOT_ENABLED':
            return t`Their number is in a country you no longer send to`;
        case 'ALREADY_BOUGHT':
            return t`Bought a ticket before it reached them`;
        case 'CAMPAIGN_STOPPED':
            return t`The campaign was stopped first`;
        case 'CONTACT_DELETED':
            return t`The contact was deleted first`;
        default:
            return t`No reason was recorded`;
    }
};

export const automationTitle = (trigger: CrmAutomationTrigger): string => ({
    EVENT_PUBLISHED: t`Announce new events`,
    AFTER_EVENT: t`Say thanks after an event`,
    BEFORE_EVENT: t`Remind people before an event`,
    ABANDONED_CHECKOUT: t`Recover abandoned checkouts`,
    SEGMENT_ENTERED: t`Email people who join a segment`,
    WIN_BACK: t`Win back lapsed regulars`,
    WELCOME: t`Welcome first-timers`,
})[trigger];

export const automationDescription = (trigger: CrmAutomationTrigger): string => ({
    EVENT_PUBLISHED: t`When you publish a new event, email your audience about it with a link to book.`,
    AFTER_EVENT: t`After an event ends, thank everyone who had a ticket.`,
    BEFORE_EVENT: t`A few days before each date, remind ticket holders, or nudge subscribers who haven't booked yet.`,
    ABANDONED_CHECKOUT: t`When someone starts booking but doesn't finish, send them back to the event.`,
    SEGMENT_ENTERED: t`When someone joins a segment, like your subscribers or your VIPs, send them an email.`,
    WIN_BACK: t`Once a day, email regulars who haven't booked in six months. Nobody gets it more than twice a year.`,
    WELCOME: t`Once a day, welcome people who booked with you for the first time. Each person gets it once.`,
})[trigger];

export const abandonedWaitLabel = (minutes: number): string => {
    switch (minutes) {
        case 180:
            return t`3 hours`;
        case 1440:
            return t`1 day`;
        default:
            return t`1 hour`;
    }
};

export const automationSummary = (automation: CrmAutomation, audience: string): string => {
    switch (automation.trigger) {
        case 'ABANDONED_CHECKOUT': {
            const wait = abandonedWaitLabel(automation.wait_minutes);
            return t`${wait} after a checkout is abandoned · ${audience}`;
        }
        case 'BEFORE_EVENT': {
            const days = automation.days_before;
            return automation.occurrence_audience === 'purchased'
                ? plural(days, {one: `# day before each date · ticket holders`, other: `# days before each date · ticket holders`})
                : plural(days, {one: `# day before each date · subscribers without a ticket`, other: `# days before each date · subscribers without a ticket`});
        }
        case 'AFTER_EVENT': {
            const days = automation.delay_days;
            return days === 0
                ? t`When each date ends · ticket holders`
                : plural(days, {one: `# day after each date ends · ticket holders`, other: `# days after each date ends · ticket holders`});
        }
        case 'SEGMENT_ENTERED':
            return t`When someone joins the segment · ${audience}`;
        case 'EVENT_PUBLISHED':
            return t`When you publish an event · ${audience}`;
        case 'WIN_BACK':
        case 'WELCOME':
            return t`Once a day · ${audience}`;
    }
};

export const reviewReasonLabel = (reason: string | null): string => {
    switch (reason) {
        case 'FIRST_CAMPAIGN':
            return t`Account's first campaign`;
        case 'UNTRUSTED_ACCOUNT':
            return t`Account not trusted yet`;
        case 'RECENT_IMPORT':
            return t`Reaches contacts from a recent import`;
        default:
            return '–';
    }
};
