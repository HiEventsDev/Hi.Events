import {Card, SegmentedControl, Skeleton, Stack, Text} from "@mantine/core";
import {t} from "@lingui/macro";
import {Link} from "react-router";
import {useGetAdminAccountFeatureFlags} from "../../../../../queries/useGetAdminAccountFeatureFlags";
import {useSetAccountFeatureFlag} from "../../../../../mutations/useSetAccountFeatureFlag";
import {AdminAccountFeatureFlag} from "../../../../../api/admin.client";
import {IdParam} from "../../../../../types";
import {showError, showSuccess} from "../../../../../utilites/notifications";
import {getFeatureFlagLabel} from "../../FeatureFlags/featureFlagLabels";
import classes from "./AccountDetail.module.scss";

const DEFAULT = 'default';
const ON = 'on';
const OFF = 'off';

const toValue = (flag: AdminAccountFeatureFlag) => {
    if (flag.override === null) {
        return DEFAULT;
    }

    return flag.override ? ON : OFF;
};

const toOverride = (value: string): boolean | null => {
    if (value === DEFAULT) {
        return null;
    }

    return value === ON;
};

export const AccountFeatureFlags = ({accountId}: { accountId: IdParam }) => {
    const {data, isLoading} = useGetAdminAccountFeatureFlags(accountId);
    const setFlagMutation = useSetAccountFeatureFlag();
    const flags = data?.data ?? [];

    const handleChange = (key: string, value: string) => {
        setFlagMutation.mutate({accountId, key, enabled: toOverride(value)}, {
            onSuccess: () => showSuccess(t`Feature flag updated`),
            onError: () => showError(t`Failed to update feature flag`),
        });
    };

    if (!isLoading && flags.length === 0) {
        return null;
    }

    return (
        <Card className={classes.accountCard}>
            <Stack gap="md">
                <div>
                    <Text size="lg" fw={600}>{t`Feature flags`}</Text>
                    <Text size="sm" c="dimmed">
                        {t`Override the global default for this account.`}{' '}
                        <Link to="/admin/feature-flags">{t`Manage defaults`}</Link>
                    </Text>
                </div>
                {isLoading && <Skeleton height={60}/>}
                {flags.map((flag) => {
                    const label = getFeatureFlagLabel(flag.key);

                    return (
                        <Stack key={flag.key} gap={6}>
                            <div>
                                <Text fw={500}>{label.name}</Text>
                                {label.description && <Text size="sm" c="dimmed">{label.description}</Text>}
                            </div>
                            <SegmentedControl
                                value={toValue(flag)}
                                onChange={(value) => handleChange(flag.key, value)}
                                disabled={setFlagMutation.isPending}
                                data-testid={`account-feature-flag-${flag.key}`}
                                data={[
                                    {
                                        value: DEFAULT,
                                        label: flag.enabled_by_default ? t`Default (on)` : t`Default (off)`,
                                    },
                                    {value: ON, label: t`On`},
                                    {value: OFF, label: t`Off`},
                                ]}
                            />
                        </Stack>
                    );
                })}
            </Stack>
        </Card>
    );
};
