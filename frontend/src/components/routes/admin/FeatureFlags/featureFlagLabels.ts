import {t} from "@lingui/macro";
import {FeatureFlag} from "../../../../constants/featureFlags.ts";

export const getFeatureFlagLabel = (key: string): { name: string, description: string } => {
    switch (key) {
        case FeatureFlag.SEATING:
            return {
                name: t`Reserved seating`,
                description: t`Lets accounts design seat maps and sell tickets by seat.`,
            };
        case FeatureFlag.BOX_OFFICE:
            return {
                name: t`Box office`,
                description: t`Lets accounts sell tickets at the door, with card readers and door check-in.`,
            };
        case FeatureFlag.ENTERPRISE_SSO:
            return {
                name: t`Enterprise single sign-on`,
                description: t`Lets accounts connect their identity provider, verify email domains and require single sign-on.`,
            };
        case FeatureFlag.CRM:
            return {
                name: t`Audience`,
                description: t`Lets accounts see every buyer and attendee as one contact, build segments and email the people who opted in.`,
            };
        case FeatureFlag.CRM_SMS:
            return {
                name: t`Audience text messages`,
                description: t`Lets accounts connect their own Twilio account and send text message campaigns to people who opted in to texts.`,
            };
        default:
            return {name: key, description: ''};
    }
};
