import {t} from "@lingui/macro";

export const selfServiceCopy = (isDefault: boolean) => isDefault
    ? {
        label: t`Enable attendee self-service by default`,
        description: t`When enabled, new events will allow attendees to manage their own ticket details via a secure link. This can be overridden per event.`,
    }
    : {
        label: t`Enable attendee self-service`,
        description: t`Allow attendees to update their ticket information (name, email) via a secure link sent with their order confirmation.`,
    };
