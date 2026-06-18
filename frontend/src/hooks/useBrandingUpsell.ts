import {IdParam} from "../types.ts";
import {useGetAccount} from "../queries/useGetAccount.ts";
import {useOrganizerPlan} from "./useOrganizerPlan.ts";

export const useBrandingUpsell = (organizerId: IdParam) => {
    const {data: account} = useGetAccount();
    const {upgrade, feesBypassed, hasFeature} = useOrganizerPlan(organizerId);

    const isEligible = Boolean(
        account?.is_saas_mode_enabled
        && upgrade
        && !feesBypassed
        && !hasFeature('branding_removal')
        && upgrade.features?.branding_removal === true
    );

    return {isEligible, upgrade};
};
