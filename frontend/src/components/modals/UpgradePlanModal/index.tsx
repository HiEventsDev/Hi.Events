import {t} from "@lingui/macro";
import {Button, Group, Text} from "@mantine/core";
import {modals} from "@mantine/modals";
import {IconArrowDown, IconArrowRight, IconCircleCheck, IconSparkles} from "@tabler/icons-react";
import {IdParam, PlanSummary} from "../../../types.ts";
import {useUpgradeOrganizerConfiguration} from "../../../mutations/useUpgradeOrganizerConfiguration.ts";
import {useDowngradeOrganizerConfiguration} from "../../../mutations/useDowngradeOrganizerConfiguration.ts";
import {planFeeLabel} from "../../../utilites/currency.ts";
import {planDisplayName} from "../../../utilites/plans.ts";
import {getConfig} from "../../../utilites/config.ts";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";
import {Callout} from "../../common/Callout";
import classes from "./UpgradePlanModal.module.scss";

interface PlanFees {
    percentage: number;
    fixed: number;
    currency: string;
}

interface UpgradePlanModalProps {
    organizerId: IdParam;
    currentName?: string;
    currentFees?: PlanFees;
    targetPlan: PlanSummary;
    passesFeesToBuyer: boolean;
}

export const UpgradePlanModalContent = ({
    organizerId,
    currentName,
    currentFees,
    targetPlan,
    passesFeesToBuyer,
}: UpgradePlanModalProps) => {
    const appName = getConfig("VITE_APP_NAME", "Hi.Events");
    const targetName = planDisplayName(targetPlan.name);
    const upgradeMutation = useUpgradeOrganizerConfiguration();

    const benefits = [
        t`Your logo on every attendee email`,
        t`No "Powered by ${appName}" on your event page, checkout or tickets`,
        t`A seamless, fully-branded experience for your attendees`,
    ];

    const handleConfirm = () => {
        upgradeMutation.mutate({organizerId}, {
            onSuccess: () => {
                showSuccess(t`You're on ${targetName}. ${appName} branding has been removed.`);
                modals.closeAll();
            },
            onError: (error: any) => {
                showError(error?.response?.data?.message ?? t`Upgrade failed. Please try again.`);
            },
        });
    };

    return (
        <div className={classes.wrapper}>
            <div className={classes.hero}>
                <div className={classes.heroIcon}>
                    <IconSparkles size={26}/>
                </div>
                <Text className={classes.heroTitle}>{t`Upgrade to ${targetName}`}</Text>
                <Text className={classes.heroSubtitle}>{t`Make every touchpoint unmistakably yours.`}</Text>
            </div>

            <div className={classes.body}>
                <ul className={classes.benefits}>
                    {benefits.map((benefit) => (
                        <li key={benefit}>
                            <IconCircleCheck size={18}/>
                            <span>{benefit}</span>
                        </li>
                    ))}
                </ul>

                <div>
                    <div className={classes.feeCompare}>
                        <div className={classes.feeCol}>
                            <span className={classes.feeLabel}>{planDisplayName(currentName) || t`Current plan`}</span>
                            <span className={classes.feeValue}>{planFeeLabel(currentFees)}</span>
                        </div>
                        <IconArrowRight size={18} className={classes.feeArrow}/>
                        <div className={`${classes.feeCol} ${classes.feeColActive}`}>
                            <span className={classes.feeLabel}>{targetName}</span>
                            <span className={classes.feeValue}>{planFeeLabel(targetPlan.application_fees)}</span>
                        </div>
                    </div>
                    <Text className={classes.feeCaption}>{t`per paid order`}</Text>
                </div>

                {passesFeesToBuyer ? (
                    <Callout variant="success">
                        {t`You pass platform fees on to your buyers, so upgrading adds nothing to your costs.`}
                    </Callout>
                ) : (
                    <Callout variant="tip">
                        {t`Pass platform fees on to your buyers and upgrading won't cost you anything extra.`}
                    </Callout>
                )}

                <Text size="xs" c="dimmed" className={classes.fineprint}>
                    {t`${appName} branding is removed from your paid events as soon as you upgrade. Free events always include ${appName} branding.`}
                </Text>

                <Group justify="flex-end" gap="sm" mt="xs">
                    <Button variant="subtle" color="gray" onClick={() => modals.closeAll()}>
                        {t`Cancel`}
                    </Button>
                    <Button
                        leftSection={<IconSparkles size={16}/>}
                        loading={upgradeMutation.isPending}
                        onClick={handleConfirm}
                    >
                        {t`Upgrade to ${targetName}`}
                    </Button>
                </Group>
            </div>
        </div>
    );
};

interface DowngradePlanModalProps {
    organizerId: IdParam;
    targetPlan: PlanSummary;
}

export const DowngradePlanModalContent = ({organizerId, targetPlan}: DowngradePlanModalProps) => {
    const appName = getConfig("VITE_APP_NAME", "Hi.Events");
    const targetName = planDisplayName(targetPlan.name);
    const downgradeMutation = useDowngradeOrganizerConfiguration();

    const handleConfirm = () => {
        downgradeMutation.mutate({organizerId}, {
            onSuccess: () => {
                showSuccess(t`You're back on ${targetName}. ${appName} branding has been restored.`);
                modals.closeAll();
            },
            onError: (error: any) => {
                showError(error?.response?.data?.message ?? t`Plan change failed. Please try again.`);
            },
        });
    };

    return (
        <div className={classes.wrapper}>
            <div className={`${classes.hero} ${classes.heroMuted}`}>
                <div className={`${classes.heroIcon} ${classes.heroIconMuted}`}>
                    <IconArrowDown size={24}/>
                </div>
                <Text className={classes.heroTitle}>{t`Switch back to ${targetName}?`}</Text>
                <Text className={classes.heroSubtitle}>
                    {t`Your platform fee changes to ${planFeeLabel(targetPlan.application_fees)} per paid order.`}
                </Text>
            </div>

            <div className={classes.body}>
                <Callout variant="warning">
                    {t`${appName} branding will reappear on your emails, event pages, checkout and tickets.`}
                </Callout>

                <Group justify="flex-end" gap="sm" mt="xs">
                    <Button variant="subtle" color="gray" onClick={() => modals.closeAll()}>
                        {t`Keep my plan`}
                    </Button>
                    <Button color="gray" loading={downgradeMutation.isPending} onClick={handleConfirm}>
                        {t`Switch plan`}
                    </Button>
                </Group>
            </div>
        </div>
    );
};
