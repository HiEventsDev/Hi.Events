import {FeatureFlag} from "../../../constants/featureFlags.ts";
import {useAccess} from "../../../hooks/useAccess.ts";
import {useLicensedFeature} from "../../licensing/hooks/useLicensedFeature.ts";

export const useCrmAccess = () => {
    const {isReadOnly} = useAccess();
    const {isEnabled, isSetupLocked, isVisible} = useLicensedFeature(FeatureFlag.CRM);

    return {
        isVisible,
        isLocked: isSetupLocked,
        canChange: !isReadOnly && isEnabled,
        canRemove: !isReadOnly,
    };
};
