import {modals} from "@mantine/modals";
import {IdParam, PlanSummary} from "../types.ts";
import {useGetOrganizerSettings} from "../queries/useGetOrganizerSettings.ts";
import {useOrganizerPlan} from "./useOrganizerPlan.ts";
import {DowngradePlanModalContent, UpgradePlanModalContent} from "../components/modals/UpgradePlanModal";

export const useOrganizerPlanActions = (organizerId: IdParam) => {
    const {configuration, upgrade, downgrade} = useOrganizerPlan(organizerId);
    const organizerSettingsQuery = useGetOrganizerSettings(organizerId);
    const passesFeesToBuyer = organizerSettingsQuery.data?.default_pass_platform_fee_to_buyer ?? false;

    const modalOptions = {
        withCloseButton: false,
        size: 'md',
        radius: 'lg',
        padding: 0,
        centered: true,
    } as const;

    const openUpgradeModal = (upgradePlan: PlanSummary = upgrade!) => {
        if (!upgradePlan) {
            return;
        }

        modals.open({
            ...modalOptions,
            children: (
                <UpgradePlanModalContent
                    organizerId={organizerId}
                    currentName={configuration?.name}
                    currentFees={configuration?.application_fees}
                    targetPlan={upgradePlan}
                    passesFeesToBuyer={passesFeesToBuyer}
                />
            ),
        });
    };

    const openDowngradeModal = (downgradePlan: PlanSummary = downgrade!) => {
        if (!downgradePlan) {
            return;
        }

        modals.open({
            ...modalOptions,
            children: (
                <DowngradePlanModalContent
                    organizerId={organizerId}
                    targetPlan={downgradePlan}
                />
            ),
        });
    };

    return {
        openUpgradeModal,
        openDowngradeModal,
    };
};
