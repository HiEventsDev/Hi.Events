import {t} from "@lingui/macro";
import {Button, ButtonProps} from "@mantine/core";
import {IconSparkles} from "@tabler/icons-react";
import {IdParam} from "../../../types.ts";
import {useOrganizerPlan} from "../../../hooks/useOrganizerPlan.ts";
import {useOrganizerPlanActions} from "../../../hooks/useOrganizerPlanActions.tsx";
import {planDisplayName} from "../../../utilites/plans.ts";

interface UpgradePlanButtonProps extends Pick<ButtonProps, 'size' | 'variant' | 'mt'> {
    organizerId: IdParam;
}

export const UpgradePlanButton = ({organizerId, ...buttonProps}: UpgradePlanButtonProps) => {
    const {upgrade, feesBypassed} = useOrganizerPlan(organizerId);
    const {openUpgradeModal} = useOrganizerPlanActions(organizerId);

    if (!upgrade || feesBypassed) {
        return null;
    }

    return (
        <Button
            leftSection={<IconSparkles size={16}/>}
            onClick={() => openUpgradeModal(upgrade)}
            {...buttonProps}
        >
            {t`Upgrade to ${planDisplayName(upgrade.name)}`}
        </Button>
    );
};
