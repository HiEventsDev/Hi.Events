import {IdParam, OrganizerFeature} from "../types.ts";
import {useGetOrganizer} from "../queries/useGetOrganizer.ts";

export const useOrganizerPlan = (organizerId: IdParam) => {
    const organizerQuery = useGetOrganizer(organizerId);
    const configuration = organizerQuery.data?.configuration;

    return {
        configuration,
        upgrade: configuration?.upgrade,
        downgrade: configuration?.downgrade,
        feesBypassed: configuration?.bypass_application_fees ?? false,
        hasFeature: (feature: OrganizerFeature) => configuration?.features?.[feature] === true,
        isLoading: organizerQuery.isLoading,
    };
};
