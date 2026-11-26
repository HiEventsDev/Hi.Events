import {FeatureFlag} from "../../../constants/featureFlags.ts";
import {useLicensedFeature} from "../../licensing/hooks/useLicensedFeature.ts";

export const useTextMessagesVisible = (): boolean => {
    const textMessages = useLicensedFeature(FeatureFlag.CRM_SMS);
    const audience = useLicensedFeature(FeatureFlag.CRM);

    return textMessages.isVisible && audience.isVisible;
};

export const useTextMessagesPaused = (): boolean => {
    const textMessages = useLicensedFeature(FeatureFlag.CRM_SMS);
    const audience = useLicensedFeature(FeatureFlag.CRM);

    return textMessages.isVisible && audience.isVisible && !(textMessages.isEnabled && audience.isEnabled);
};
