import {ActionIcon, Anchor, Badge, Button, Container, Group, Skeleton, Stack, Switch, Text, Title} from "@mantine/core";
import {t} from "@lingui/macro";
import {useState} from "react";
import {Link} from "react-router";
import {IconChevronDown, IconChevronUp, IconX} from "@tabler/icons-react";
import {useGetAdminFeatureFlags} from "../../../../queries/useGetAdminFeatureFlags";
import {useGetAdminFeatureFlagOverrides} from "../../../../queries/useGetAdminFeatureFlagOverrides";
import {useUpdateFeatureFlag} from "../../../../mutations/useUpdateFeatureFlag";
import {useSetAccountFeatureFlag} from "../../../../mutations/useSetAccountFeatureFlag";
import {AdminFeatureFlag} from "../../../../api/admin.client";
import {showError, showSuccess} from "../../../../utilites/notifications";
import {Callout} from "../../../common/Callout";
import {getFeatureFlagLabel} from "./featureFlagLabels";
import classes from "./FeatureFlags.module.scss";

const Overrides = ({flagKey}: { flagKey: string }) => {
    const {data, isLoading} = useGetAdminFeatureFlagOverrides(flagKey, true);
    const setOverrideMutation = useSetAccountFeatureFlag();
    const overrides = data?.data ?? [];

    const handleRemove = (accountId: number) => {
        setOverrideMutation.mutate({accountId, key: flagKey, enabled: null}, {
            onSuccess: () => showSuccess(t`Override removed`),
            onError: () => showError(t`Failed to remove override`),
        });
    };

    if (isLoading) {
        return <Skeleton height={60} className={classes.overrides}/>;
    }

    return (
        <div className={classes.overrides}>
            {overrides.length === 0 && (
                <Text size="sm" c="dimmed">
                    {t`No account overrides. Add one from an account's admin page.`}
                </Text>
            )}
            {overrides.map((override) => (
                <div key={override.account_id} className={classes.overrideRow} data-testid={`feature-flag-override-${override.account_id}`}>
                    <Anchor component={Link} to={`/admin/accounts/${override.account_id}`} size="sm">
                        {override.account_name}
                    </Anchor>
                    <Group gap="sm">
                        <Badge color={override.enabled ? 'green' : 'gray'} variant="light">
                            {override.enabled ? t`On` : t`Off`}
                        </Badge>
                        <ActionIcon
                            variant="subtle"
                            color="red"
                            aria-label={t`Remove override`}
                            onClick={() => handleRemove(override.account_id)}
                            disabled={setOverrideMutation.isPending}
                        >
                            <IconX size={14}/>
                        </ActionIcon>
                    </Group>
                </div>
            ))}
        </div>
    );
};

const FeatureFlagCard = ({flag}: { flag: AdminFeatureFlag }) => {
    const [isExpanded, setIsExpanded] = useState(false);
    const updateMutation = useUpdateFeatureFlag();
    const label = getFeatureFlagLabel(flag.key);
    const overrideCount = flag.enabled_override_count + flag.disabled_override_count;

    const handleDefaultChange = (enabledByDefault: boolean) => {
        updateMutation.mutate({key: flag.key, enabledByDefault}, {
            onSuccess: () => showSuccess(t`Default updated`),
            onError: () => showError(t`Failed to update default`),
        });
    };

    return (
        <div className={classes.flagCard}>
            <div className={classes.flagHeader}>
                <Stack gap={4}>
                    <Group gap="sm">
                        <Text fw={600}>{label.name}</Text>
                        <Badge variant="outline" color="gray" size="sm">{flag.key}</Badge>
                    </Group>
                    {label.description && <Text size="sm" c="dimmed">{label.description}</Text>}
                    <Group gap="xs" mt={4}>
                        <Badge color="green" variant="light" size="sm">
                            {t`${flag.enabled_override_count} forced on`}
                        </Badge>
                        <Badge color="gray" variant="light" size="sm">
                            {t`${flag.disabled_override_count} forced off`}
                        </Badge>
                    </Group>
                </Stack>
                <Switch
                    label={t`Enabled for everyone`}
                    labelPosition="left"
                    checked={flag.enabled_by_default}
                    onChange={(event) => handleDefaultChange(event.currentTarget.checked)}
                    disabled={updateMutation.isPending}
                    data-testid={`feature-flag-default-switch-${flag.key}`}
                />
            </div>
            <Button
                variant="subtle"
                size="compact-sm"
                mt="sm"
                rightSection={isExpanded ? <IconChevronUp size={14}/> : <IconChevronDown size={14}/>}
                onClick={() => setIsExpanded(!isExpanded)}
                disabled={overrideCount === 0 && !isExpanded}
                data-testid={`feature-flag-overrides-toggle-${flag.key}`}
            >
                {t`Account overrides (${overrideCount})`}
            </Button>
            {isExpanded && <Overrides flagKey={flag.key}/>}
        </div>
    );
};

const FeatureFlags = () => {
    const {data, isLoading} = useGetAdminFeatureFlags();
    const flags = data?.data ?? [];

    return (
        <Container size="xl" p="xl">
            <Stack gap="lg">
                <Title order={1}>{t`Feature Flags`}</Title>
                <Callout variant="info">
                    {t`The default applies to every account. An account override always wins over the default.`}
                </Callout>
                {isLoading && <Skeleton height={120} radius="md"/>}
                {flags.map((flag) => <FeatureFlagCard key={flag.key} flag={flag}/>)}
            </Stack>
        </Container>
    );
};

export default FeatureFlags;
