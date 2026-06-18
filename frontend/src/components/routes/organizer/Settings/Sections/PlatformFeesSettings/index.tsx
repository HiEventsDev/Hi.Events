import {t} from "@lingui/macro";
import {useParams} from "react-router";
import {useEffect, useState} from "react";
import {Anchor, Badge, Group} from "@mantine/core";
import {showSuccess} from "../../../../../../utilites/notifications.tsx";
import {useGetOrganizerSettings} from "../../../../../../queries/useGetOrganizerSettings.ts";
import {useUpdateOrganizerSettings} from "../../../../../../mutations/useUpdateOrganizerSettings.ts";
import {useGetOrganizer} from "../../../../../../queries/useGetOrganizer.ts";
import {useOrganizerPlan} from "../../../../../../hooks/useOrganizerPlan.ts";
import {useOrganizerPlanActions} from "../../../../../../hooks/useOrganizerPlanActions.tsx";
import {UpgradePlanButton} from "../../../../../common/UpgradePlanButton";
import {PlatformFeesSettings as PlatformFeesSettingsBase} from "../../../../../common/PlatformFeesSettings";
import {planDisplayName} from "../../../../../../utilites/plans.ts";

export const PlatformFeesSettings = () => {
    const {organizerId} = useParams();
    const organizerSettingsQuery = useGetOrganizerSettings(organizerId);
    const organizerQuery = useGetOrganizer(organizerId);
    const updateMutation = useUpdateOrganizerSettings();
    const {downgrade, hasFeature} = useOrganizerPlan(organizerId);
    const {openDowngradeModal} = useOrganizerPlanActions(organizerId);
    const [currentValue, setCurrentValue] = useState(false);

    useEffect(() => {
        if (organizerSettingsQuery?.isFetched && organizerSettingsQuery?.data) {
            setCurrentValue(organizerSettingsQuery.data.default_pass_platform_fee_to_buyer ?? false);
        }
    }, [organizerSettingsQuery.isFetched, organizerSettingsQuery.data]);

    const handleSave = (passToBuyer: boolean) => {
        updateMutation.mutate({
            organizerSettings: { default_pass_platform_fee_to_buyer: passToBuyer },
            organizerId: organizerId,
        }, {
            onSuccess: () => {
                showSuccess(t`Successfully Updated Platform Fee Defaults`);
                setCurrentValue(passToBuyer);
            },
        });
    };

    const planSlot = (
        <>
            {hasFeature('branding_removal') && (
                <Group gap={6} mt={4}>
                    <Badge color="teal" size="sm" variant="light">{t`Includes branding removal`}</Badge>
                </Group>
            )}
            <Group gap="sm" mt="sm">
                <UpgradePlanButton organizerId={organizerId} size="xs" variant="light"/>
                {downgrade && (
                    <Anchor size="xs" c="dimmed" underline="always" onClick={() => openDowngradeModal(downgrade)}>
                        {t`Switch back to the ${planDisplayName(downgrade.name)} plan`}
                    </Anchor>
                )}
            </Group>
        </>
    );

    return (
        <PlatformFeesSettingsBase
            configuration={organizerQuery.data?.configuration}
            currentValue={currentValue}
            onSave={handleSave}
            isLoading={organizerSettingsQuery.isLoading}
            isSaving={updateMutation.isPending}
            heading={t`Plan & Fees`}
            description={t`Your plan, its platform fee, and how fees are handled for new events.`}
            feeHandlingLabel={t`Default Fee Handling`}
            feeHandlingDescription={t`Choose the default setting for new events. This can be overridden for individual events.`}
            planSlot={planSlot}
        />
    );
};
