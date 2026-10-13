import {t} from "@lingui/macro";

export const getLifecycleEmailLabel = (nudgeType: string): { name: string, trigger: string, destination: string } => {
    switch (nudgeType) {
        case 'NO_EVENT_CREATED':
            return {
                name: t`No event created`,
                trigger: t`3 days after signup, if the account owner has confirmed their email but hasn't created an event.`,
                destination: t`Event setup`,
            };
        case 'STRIPE_ONBOARDING_INCOMPLETE':
            return {
                name: t`Stripe setup not finished`,
                trigger: t`24 hours after an organizer creates a paid ticket or starts connecting Stripe, if Stripe still isn't connected. Skipped for countries Stripe doesn't support.`,
                destination: t`Organizer payout settings`,
            };
        case 'EVENT_DRAFT_STALE':
            return {
                name: t`Draft event not published`,
                trigger: t`3 days after a draft event is created, if nothing on the account is live.`,
                destination: t`Event dashboard`,
            };
        case 'LIVE_EVENT_NO_SALES':
            return {
                name: t`Live event with no sales`,
                trigger: t`7 days after a live upcoming paid event was last edited or its organizer connected Stripe, whichever is later, if the account has never had an order.`,
                destination: t`Event dashboard`,
            };
        default:
            return {name: nudgeType, trigger: '', destination: ''};
    }
};
