import {ReactNode} from "react";
import {t} from "@lingui/macro";
import {Anchor, Group, Text} from "@mantine/core";
import {IdParam, OrganizerFeature} from "../../../types.ts";
import {useOrganizerPlan} from "../../../hooks/useOrganizerPlan.ts";
import {getConfig} from "../../../utilites/config.ts";
import {planDisplayName} from "../../../utilites/plans.ts";
import {UpgradePlanButton} from "../UpgradePlanButton";

interface FeatureGateProps {
    organizerId: IdParam;
    feature: OrganizerFeature;
    teaser?: ReactNode;
    children: ReactNode;
}

const scrollToPlanSection = () => {
    if (typeof document !== "undefined") {
        document.getElementById('platform-fees')?.scrollIntoView({behavior: 'smooth'});
    }
};

export const FeatureGate = ({organizerId, feature, teaser, children}: FeatureGateProps) => {
    const supportEmail = getConfig("VITE_PLATFORM_SUPPORT_EMAIL", "hello@hi.events");
    const {upgrade, feesBypassed, hasFeature} = useOrganizerPlan(organizerId);

    if (hasFeature(feature)) {
        return <>{children}</>;
    }

    return (
        <>
            {teaser}

            {feesBypassed && (
                <Text size="sm" c="dimmed" mt="md">
                    {t`This feature is not available because platform fees are disabled for your account.`}
                </Text>
            )}

            {!feesBypassed && upgrade && (
                <>
                    <Group gap="sm" mt="md">
                        <UpgradePlanButton organizerId={organizerId}/>
                        <Anchor size="sm" c="dimmed" onClick={scrollToPlanSection}>
                            {t`View plan details`}
                        </Anchor>
                    </Group>
                    <Text size="xs" c="dimmed" mt="xs">
                        {t`Included in the ${planDisplayName(upgrade.name)} plan`}
                    </Text>
                </>
            )}

            {!feesBypassed && !upgrade && (
                <Text size="sm" c="dimmed" mt="md">
                    {t`This feature isn't included in your plan. Get in touch and we'll set you up.`}
                    {' '}
                    <Anchor href={`mailto:${supportEmail}`}>
                        {t`Contact us`}
                    </Anchor>
                </Text>
            )}
        </>
    );
};
