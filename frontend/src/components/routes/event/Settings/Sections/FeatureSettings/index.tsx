import {ReactNode} from "react";
import {t} from "@lingui/macro";
import {Switch} from "@mantine/core";
import {useParams} from "react-router";
import {IconIdBadge2} from "@tabler/icons-react";
import {showError, showSuccess} from "../../../../../../utilites/notifications.tsx";
import {useUpdateEventSettings} from "../../../../../../mutations/useUpdateEventSettings.ts";
import {useGetEventSettings} from "../../../../../../queries/useGetEventSettings.ts";
import {EventFeature} from "../../../../../../types.ts";
import {SettingsPage, SettingsRow, SettingsSection} from "../../../../../common/SettingsPage";
import classes from "./FeatureSettings.module.scss";

interface FeatureOption {
    feature: EventFeature;
    icon: ReactNode;
    label: string;
    description: string;
}

export const FeatureSettings = () => {
    const {eventId} = useParams();
    const settingsQuery = useGetEventSettings(eventId);
    const updateMutation = useUpdateEventSettings();
    const enabledFeatures = settingsQuery.data?.enabled_features ?? [];

    const options: FeatureOption[] = [
        {
            feature: 'BADGES',
            icon: <IconIdBadge2 size={16}/>,
            label: t`Name badges`,
            description: t`Design name badges, print them in batches, and print them at check-in.`,
        },
    ];

    const toggle = (option: FeatureOption, enabled: boolean) => {
        const next = enabled
            ? [...enabledFeatures, option.feature]
            : enabledFeatures.filter((feature) => feature !== option.feature);

        const featureName = option.label;
        updateMutation.mutate({eventId, eventSettings: {enabled_features: next}}, {
            onSuccess: () => showSuccess(enabled ? t`${featureName} turned on` : t`${featureName} turned off`),
            onError: () => showError(t`We couldn't update this feature. Please try again.`),
        });
    };

    return (
        <SettingsPage
            title={t`Features`}
            description={t`Turn on the extra tools this event needs. Features that are off are hidden from the menu and pages.`}
        >
            <SettingsSection>
                {options.map((option) => {
                    const switchId = `event-feature-${option.feature.toLowerCase()}`;
                    return (
                        <SettingsRow
                            key={option.feature}
                            label={<span className={classes.label}>{option.icon}{option.label}</span>}
                            description={option.description}
                            htmlFor={switchId}
                            inlineControl
                        >
                            <Switch
                                id={switchId}
                                checked={enabledFeatures.includes(option.feature)}
                                disabled={settingsQuery.isLoading || updateMutation.isPending}
                                onChange={(event) => toggle(option, event.currentTarget.checked)}
                                data-testid={`${switchId}-switch`}
                            />
                        </SettingsRow>
                    );
                })}
            </SettingsSection>
        </SettingsPage>
    );
};
