import {useGetMe} from "../../../queries/useGetMe.ts";
import {useIsFeatureEnabled} from "../../../hooks/useIsFeatureEnabled.ts";
import {FeatureFlagKey, licensedFeatureOf} from "../../../constants/featureFlags.ts";

export interface LicensedFeatureAccess {
    isEnabled: boolean;
    isUnlicensed: boolean;
    isSetupLocked: boolean;
    isLocked: boolean;
    isVisible: boolean;
}

export const useLicensedFeature = (feature: FeatureFlagKey): LicensedFeatureAccess => {
    const {data: me} = useGetMe();
    const isEnabled = useIsFeatureEnabled(feature);
    const licence = me?.licence;
    const licensedFeature = licensedFeatureOf(feature);
    const isUnlicensed = !!licence && !licence.unlocked_features?.includes(licensedFeature);
    const isSetupLocked = !isEnabled && isUnlicensed;
    const isLocked = isSetupLocked && !!licence?.features_in_use?.includes(licensedFeature);

    return {isEnabled, isUnlicensed, isSetupLocked, isLocked, isVisible: isEnabled || isLocked};
};
