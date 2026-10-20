import {Button, Loader} from "@mantine/core";
import {t, Trans} from "@lingui/macro";
import {useEffect, useRef, useState} from "react";
import {useLocation, useNavigate} from "react-router";
import {SECURITY_SETTINGS_PATH} from "../../../../api/client.ts";
import {resumeUrl} from "../../../../hooks/useResumeAction.ts";
import {
    useDeleteSsoIdentity,
    useSendPasswordSetupLink,
    useStartSelfServiceSso,
} from "../../../../mutations/useSsoMutations.ts";
import {useGetSsoSignInMethods} from "../../../../queries/useGetSsoIdentities.ts";
import {useGetSsoProviders} from "../../../../queries/useGetSsoProviders.ts";
import {IdentityConfirmation, SsoProvider, User, UserIdentity} from "../../../../types.ts";
import {relativeDate} from "../../../../utilites/dates.ts";
import {showError, showSuccess} from "../../../../utilites/notifications.tsx";
import {ssoError} from "../../../../utilites/ssoErrors.ts";
import {ConfirmIdentityModal} from "../../../common/ConfirmIdentity/ConfirmIdentityModal.tsx";
import {SettingsRow} from "../../../common/SettingsPage";
import {ProviderMark} from "../../../common/SsoButtons/ProviderMark.tsx";
import classes from "./Security.module.scss";

type PendingAction =
    | { kind: 'connect', provider: SsoProvider }
    | { kind: 'disconnect', provider: SsoProvider, identity: UserIdentity };

interface SignInMethodsProps {
    me: User;
    resumeAction: string | null;
    onChangePassword: () => void;
}

export const SignInMethods = ({me, resumeAction, onChangePassword}: SignInMethodsProps) => {
    const location = useLocation();
    const navigate = useNavigate();
    const {data: sso} = useGetSsoProviders();
    const {
        data: signInMethods,
        isError: identitiesFailed,
        isFetching: isFetchingIdentities,
        refetch: refetchIdentities,
    } = useGetSsoSignInMethods();
    const loadedIdentities = signInMethods?.identities;
    const identities = loadedIdentities ?? [];
    const linkableConnections = signInMethods?.linkableConnections ?? [];
    const [pendingAction, setPendingAction] = useState<PendingAction | null>(null);
    const connectedNoticeShown = useRef(false);
    const startSso = useStartSelfServiceSso();
    const deleteIdentity = useDeleteSsoIdentity();
    const sendPasswordLink = useSendPasswordSetupLink();
    const organizationProviders = [...identities.map(identity => identity.organization_provider), ...linkableConnections]
        .filter((provider, index, all): provider is SsoProvider => !!provider && all.findIndex(other => other?.key === provider.key) === index);
    const connectionAccountName = (provider: SsoProvider) => linkableConnections.find(connection => connection.key === provider.key)?.account_name;
    const providers = [...(sso?.providers ?? []), ...organizationProviders];
    const passwordLoginEnabled = sso?.password_login_enabled ?? true;
    const hasPassword = me.has_password !== false;
    const ssoRequired = !hasPassword && me.password_reset_allowed === false;
    const usableIdentities = identities.filter(identity => providers.some(provider => provider.key === identity.provider_key));
    const signInMethodCount = usableIdentities.length + (hasPassword && passwordLoginEnabled ? 1 : 0);

    useEffect(() => {
        const connected = (location.state as { ssoConnected?: string } | null)?.ssoConnected;

        if (connected && !connectedNoticeShown.current) {
            connectedNoticeShown.current = true;
            showSuccess(t`${connected} is connected. You can now use it to sign in.`);
            navigate(location.pathname, {replace: true, state: null});
        }
    }, []);

    useEffect(() => {
        if (!resumeAction || providers.length === 0) {
            return;
        }

        const [kind, value] = resumeAction.split(':');
        const provider = providers.find(item => kind === 'connect'
            ? item.key === value
            : item.key === identities.find(identity => String(identity.id) === value)?.provider_key);
        const identity = identities.find(item => String(item.id) === value);

        if (kind === 'connect' && provider) {
            setPendingAction({kind: 'connect', provider});
        }

        if (kind === 'disconnect' && provider && identity) {
            setPendingAction({kind: 'disconnect', provider, identity});
        }
    }, [resumeAction, providers.length, identities.length]);

    const passwordRow = passwordLoginEnabled && (
        <SettingsRow
            label={t`Password`}
            description={hasPassword
                ? t`Use a long password that you don't use anywhere else.`
                : ssoRequired
                    ? t`Your organization requires single sign-on, so you don't need a password.`
                    : t`You sign in without a password. Add one as a backup way to sign in.`}
        >
            {ssoRequired ? null : hasPassword ? (
                <Button variant="default" onClick={onChangePassword} data-testid="change-password-button">
                    {t`Change password`}
                </Button>
            ) : (
                <Button
                    variant="default"
                    loading={sendPasswordLink.isPending}
                    data-testid="set-password-button"
                    onClick={() => sendPasswordLink.mutate(me.email, {
                        onSuccess: () => showSuccess(t`We sent a link to ${me.email}. Open it to set your password.`),
                        onError: () => showError(t`Something went wrong. Please try again.`),
                    })}
                >
                    {t`Email me a link to set a password`}
                </Button>
            )}
        </SettingsRow>
    );

    const confirm = (confirmation: IdentityConfirmation, setError: (message: string | null) => void) => {
        if (!pendingAction) {
            return;
        }

        const onError = (requestError: any) => setError(requestError?.response?.data?.errors?.password?.[0] ?? ssoError(requestError).message);

        if (pendingAction.kind === 'connect') {
            startSso.mutate({
                provider: pendingAction.provider,
                intent: 'LINK',
                returnPath: SECURITY_SETTINGS_PATH,
                confirmation,
            }, {onError});
            return;
        }

        deleteIdentity.mutate({identityId: pendingAction.identity.id, confirmation}, {
            onSuccess: () => {
                showSuccess(t`${pendingAction.provider.name} was disconnected.`);
                setPendingAction(null);
            },
            onError,
        });
    };

    const renderProviders = () => {
        if (loadedIdentities) {
            return providers.map(renderProvider);
        }

        if (identitiesFailed) {
            return (
                <SettingsRow label={t`Sign-in options`} description={t`Couldn't load your security settings`}>
                    <Button
                        variant="default"
                        loading={isFetchingIdentities}
                        onClick={() => refetchIdentities()}
                        data-testid="sso-identities-retry"
                    >
                        {t`Try again`}
                    </Button>
                </SettingsRow>
            );
        }

        if (providers.length === 0) {
            return null;
        }

        return (
            <SettingsRow label={t`Sign-in options`}>
                <Loader size="sm"/>
            </SettingsRow>
        );
    };

    const renderProvider = (provider: SsoProvider) => {
        const connected = identities.filter(identity => identity.provider_key === provider.key);
        const canDisconnect = signInMethodCount > 1;
        const accountName = connectionAccountName(provider);

        return (
            <SettingsRow
                key={provider.key}
                label={(
                    <span className={classes.providerLabel}>
                        <ProviderMark provider={provider}/>
                        {provider.name}
                    </span>
                )}
                description={connected.length === 0
                    ? (accountName ? t`Not connected. ${accountName} uses this for single sign-on.` : t`Not connected`)
                    : (!canDisconnect ? t`This is how you sign in. Add a password or another option before removing it.` : undefined)}
                below={connected.length > 0 && (
                    <ul className={classes.devices}>
                        {connected.map(identity => (
                            <li key={identity.id} className={classes.device} data-testid={`sso-identity-${provider.key}`}>
                                <div className={classes.deviceIcon}><ProviderMark provider={provider} size={16}/></div>
                                <div className={classes.deviceText}>
                                    <div className={classes.deviceName} title={identity.email ?? undefined}>
                                        <span className={classes.identityEmail}>{identity.email ?? provider.name}</span>
                                    </div>
                                    {identity.last_used_at && (
                                        <div className={classes.deviceMeta}>
                                            <span><Trans>Last used {relativeDate(identity.last_used_at)}</Trans></span>
                                        </div>
                                    )}
                                </div>
                                {canDisconnect && (
                                    <Button
                                        variant="subtle"
                                        color="red"
                                        size="compact-sm"
                                        onClick={() => setPendingAction({kind: 'disconnect', provider, identity})}
                                        data-testid={`sso-disconnect-${provider.key}`}
                                    >
                                        {t`Disconnect`}
                                    </Button>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            >
                {connected.length === 0 && (
                    <Button
                        variant="default"
                        onClick={() => setPendingAction({kind: 'connect', provider})}
                        data-testid={`sso-connect-${provider.key}`}
                    >
                        {t`Connect`}
                    </Button>
                )}
            </SettingsRow>
        );
    };

    return (
        <>
            {passwordRow}
            {renderProviders()}

            {pendingAction && (
                <ConfirmIdentityModal
                    heading={pendingAction.kind === 'connect'
                        ? t`Connect ${pendingAction.provider.name}`
                        : t`Disconnect ${pendingAction.provider.name}`}
                    description={pendingAction.kind === 'connect'
                        ? t`Confirm it's you, then sign in with ${pendingAction.provider.name} to connect it.`
                        : t`You won't be able to sign in with ${pendingAction.provider.name} any more. Other devices signed in to your account will be signed out.`}
                    confirmLabel={pendingAction.kind === 'connect' ? t`Continue` : t`Disconnect`}
                    danger={pendingAction.kind === 'disconnect'}
                    resumeUrl={resumeUrl(SECURITY_SETTINGS_PATH, pendingAction.kind === 'connect'
                        ? `connect:${pendingAction.provider.key}`
                        : `disconnect:${pendingAction.identity.id}`)}
                    isSubmitting={startSso.isPending || deleteIdentity.isPending}
                    onConfirm={confirm}
                    onClose={() => setPendingAction(null)}
                />
            )}
        </>
    );
};
