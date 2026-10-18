import {Select, Switch} from "@mantine/core";
import {t} from "@lingui/macro";
import {useSaveSsoConnection, useSetSsoConnectionStatus} from "../../mutations/useAccountSsoMutations.ts";
import {AccountSsoConnection, AccountSsoConnectionRequest, IdParam} from "../../../../types.ts";
import {showError, showSuccess} from "../../../../utilites/notifications.tsx";
import {roleLabel} from "../../../../components/common/TeamAccessFields";
import {connectionRequest} from "./ConnectionForm.tsx";
import {connectionProviderName} from "./providers.tsx";
import classes from "./SingleSignOn.module.scss";

const SESSION_LENGTHS = [60, 240, 480, 720, 1440, 10080];

const sessionLengthLabel = (minutes: number): string => {
    if (minutes >= 1440) {
        const days = minutes / 1440;
        return days === 1 ? t`1 day` : t`${days} days`;
    }

    const hours = minutes / 60;
    return hours === 1 ? t`1 hour` : t`${hours} hours`;
};

const errorMessage = (error: any): string => {
    const errors = error?.response?.data?.errors ?? {};
    const first = Object.values(errors)[0] as string[] | string | undefined;

    return (Array.isArray(first) ? first[0] : first) ?? error?.response?.data?.message ?? t`Something went wrong. Please try again.`;
};

interface AccessSettingsProps {
    accountId: IdParam;
    connection: AccountSsoConnection;
    setupLocked: boolean;
}

export const AccessSettings = ({accountId, connection, setupLocked}: AccessSettingsProps) => {
    const saveMutation = useSaveSsoConnection();
    const statusMutation = useSetSsoConnectionStatus();
    const providerName = connectionProviderName(connection.type);
    const isActive = connection.status === 'ACTIVE';
    const sessionOptions = [...new Set([...SESSION_LENGTHS, connection.session_ttl_minutes])]
        .sort((a, b) => a - b)
        .map(minutes => ({value: String(minutes), label: sessionLengthLabel(minutes)}));

    const save = (overrides: Partial<AccountSsoConnectionRequest>) => {
        saveMutation.mutate({accountId, request: connectionRequest(connection, overrides), isNew: false}, {
            onSuccess: () => showSuccess(t`Single sign-on settings updated`),
            onError: (error) => showError(errorMessage(error)),
        });
    };

    const setActive = (active: boolean) => {
        statusMutation.mutate({accountId, status: active ? 'ACTIVE' : 'INACTIVE'}, {
            onSuccess: () => showSuccess(active
                ? t`Your team can now sign in with ${providerName}`
                : t`Sign-in with ${providerName} is turned off`),
            onError: (error) => showError(errorMessage(error)),
        });
    };

    return (
        <div className={classes.settingsList}>
            <div className={classes.settingRow}>
                <div className={classes.settingText}>
                    <div className={classes.settingLabel}>{t`Allow sign-in with ${providerName}`}</div>
                    <div className={classes.muted}>
                        {connection.last_tested_at
                            ? t`People on your verified domains are sent to ${providerName} when they choose Sign in with SSO.`
                            : t`Run a successful test sign-in before turning this on.`}
                    </div>
                </div>
                <Switch
                    size="md"
                    checked={isActive}
                    disabled={statusMutation.isPending || (!isActive && (setupLocked || !connection.last_tested_at)) || (isActive && connection.enforce_sso)}
                    onChange={(event) => setActive(event.currentTarget.checked)}
                    aria-label={t`Allow sign-in with ${providerName}`}
                    data-testid="sso-connection-active-switch"
                />
            </div>

            <div className={classes.settingRow}>
                <div className={classes.settingText}>
                    <div className={classes.settingLabel}>{t`Add new people automatically`}</div>
                    <div className={classes.muted}>
                        {t`Anyone ${providerName} lets in with an email on a verified domain joins this account the first time they sign in. Removed or deactivated members are never added back.`}
                    </div>
                </div>
                <Switch
                    size="md"
                    checked={connection.jit_provisioning_enabled}
                    disabled={setupLocked || saveMutation.isPending}
                    onChange={(event) => save({jit_provisioning_enabled: event.currentTarget.checked})}
                    aria-label={t`Add new people automatically`}
                    data-testid="sso-jit-switch"
                />
            </div>

            <div className={classes.settingFields}>
                <Select
                    label={t`Role for new people`}
                    data={(['ORGANIZER', 'VIEWER', 'ADMIN'] as const).map(role => ({value: role, label: roleLabel(role)}))}
                    value={connection.jit_default_role}
                    onChange={(role) => role && save({jit_default_role: role as AccountSsoConnection['jit_default_role']})}
                    disabled={setupLocked || !connection.jit_provisioning_enabled || saveMutation.isPending}
                    allowDeselect={false}
                    description={connection.jit_default_role === 'ADMIN' ? t`The account owner is emailed whenever someone joins as an admin.` : undefined}
                />
                <Select
                    label={t`Sign people out after`}
                    data={sessionOptions}
                    value={String(connection.session_ttl_minutes)}
                    onChange={(minutes) => minutes && save({session_ttl_minutes: Number(minutes)})}
                    disabled={setupLocked || saveMutation.isPending}
                    allowDeselect={false}
                    description={t`Applies to sessions started through ${providerName}. People then sign in again.`}
                />
            </div>
        </div>
    );
};
