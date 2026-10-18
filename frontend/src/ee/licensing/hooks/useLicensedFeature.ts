import {useGetMe} from "../../../queries/useGetMe.ts";
import {useIsFeatureEnabled} from "../../../hooks/useIsFeatureEnabled.ts";
import {FeatureFlagKey} from "../../../constants/featureFlags.ts";

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
    const isUnlicensed = licence?.status === 'LAPSED' || licence?.status === 'NONE';
    const isSetupLocked = !isEnabled && isUnlicensed;
    const isLocked = isSetupLocked && !!licence?.features_in_use?.includes(feature);

    return {isEnabled, isUnlicensed, isSetupLocked, isLocked, isVisible: isEnabled || isLocked};
};
