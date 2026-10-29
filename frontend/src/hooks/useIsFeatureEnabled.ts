import {useGetMe} from "../queries/useGetMe.ts";
import {FeatureFlagKey} from "../constants/featureFlags.ts";

export const useIsFeatureEnabled = (flag: FeatureFlagKey): boolean => {
    const {data: me} = useGetMe();

    return me?.feature_flags?.[flag] ?? false;
};
