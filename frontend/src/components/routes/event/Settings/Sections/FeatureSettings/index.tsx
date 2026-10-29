import {ReactNode} from "react";
import {t} from "@lingui/macro";
import {Switch} from "@mantine/core";
import {useParams} from "react-router";
import {IconIdBadge2} from "@tabler/icons-react";
import {Card} from "../../../../../common/Card";
import {HeadingWithDescription} from "../../../../../common/Card/CardHeading";
import {showError, showSuccess} from "../../../../../../utilites/notifications.tsx";
import {useUpdateEventSettings} from "../../../../../../mutations/useUpdateEventSettings.ts";
import {useGetEventSettings} from "../../../../../../queries/useGetEventSettings.ts";
import {EventFeature} from "../../../../../../types.ts";
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
            icon: <IconIdBadge2 size={18}/>,
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
        <Card>
            <HeadingWithDescription
                heading={t`Features`}
                description={t`Turn on the extra tools this event needs. Features that are off are hidden from the menu and pages.`}
            />
            <div className={classes.list}>
                {options.map((option) => (
                    <label key={option.feature} className={classes.row}>
                        <span className={classes.icon}>{option.icon}</span>
                        <span className={classes.text}>
                            <span className={classes.label}>{option.label}</span>
                            <span className={classes.description}>{option.description}</span>
                        </span>
                        <Switch
                            size="md"
                            checked={enabledFeatures.includes(option.feature)}
                            disabled={settingsQuery.isLoading || updateMutation.isPending}
                            onChange={(event) => toggle(option, event.currentTarget.checked)}
                            aria-label={option.label}
                            data-testid={`event-feature-${option.feature.toLowerCase()}-switch`}
                        />
                    </label>
                ))}
            </div>
        </Card>
    );
};
