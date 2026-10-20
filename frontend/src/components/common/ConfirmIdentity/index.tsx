import {PasswordInput} from "@mantine/core";
import {t} from "@lingui/macro";
import {IconCircleCheck} from "@tabler/icons-react";
import {useEffect, useState} from "react";
import {useGetMe} from "../../../queries/useGetMe.ts";
import {useGetSsoIdentities} from "../../../queries/useGetSsoIdentities.ts";
import {useGetSsoProviders} from "../../../queries/useGetSsoProviders.ts";
import {IdentityConfirmation, SsoProvider} from "../../../types.ts";
import {clearReauthProof, getReauthProof} from "../../../utilites/ssoFlow.ts";
import {useStartSelfServiceSso} from "../../../mutations/useSsoMutations.ts";
import {ssoError} from "../../../utilites/ssoErrors.ts";
import {SsoButtons} from "../SsoButtons";
import classes from "./ConfirmIdentity.module.scss";

export const useIdentityConfirmation = () => {
    const {data: me} = useGetMe();
    const [password, setPassword] = useState('');
    const [error, setErrorMessage] = useState<string | null>(null);
    const [storedProof, setStoredProof] = useState<string | null>(() => typeof window === 'undefined' ? null : getReauthProof());

    useEffect(() => {
        clearReauthProof();
    }, []);
    const hasPassword = me?.has_password !== false;
    const reauthProof = hasPassword ? null : storedProof;

    const setError = (message: string | null) => {
        setErrorMessage(message);

        if (message !== null) {
            setStoredProof(null);
        }
    };

    const confirmation: IdentityConfirmation | null = hasPassword
        ? (password.length > 0 ? {password} : null)
        : (reauthProof ? {reauth_proof: reauthProof} : null);

    return {
        hasPassword,
        password,
        setPassword: (value: string) => {
            setPassword(value);
            setErrorMessage(null);
        },
        error,
        setError,
        confirmation,
    };
};

export type IdentityConfirmationState = ReturnType<typeof useIdentityConfirmation>;

interface ConfirmIdentityFieldProps {
    identity: IdentityConfirmationState;
    resumeUrl: string;
    passwordTestId?: string;
    autoFocus?: boolean;
    showHint?: boolean;
}

export const ConfirmIdentityField = ({identity, resumeUrl, passwordTestId, autoFocus, showHint = true}: ConfirmIdentityFieldProps) => {
    const {data: sso} = useGetSsoProviders();
    const {data: identities} = useGetSsoIdentities(!identity.hasPassword);
    const startSso = useStartSelfServiceSso();

    if (identity.hasPassword) {
        return (
            <PasswordInput
                label={t`Current password`}
                value={identity.password}
                onChange={(event) => identity.setPassword(event.currentTarget.value)}
                error={identity.error}
                autoComplete="current-password"
                autoFocus={autoFocus}
                required
                data-testid={passwordTestId}
            />
        );
    }

    if (identity.confirmation) {
        return (
            <div className={classes.confirmed} role="status">
                <IconCircleCheck size={18}/>
                {t`You confirmed it's you. Continue within a few minutes.`}
            </div>
        );
    }

    const linkedKeys = new Set((identities ?? []).map(linked => linked.provider_key));
    const organizationProviders = (identities ?? [])
        .map(linked => linked.organization_provider)
        .filter((provider, index, all): provider is SsoProvider => !!provider && all.findIndex(other => other?.key === provider.key) === index);
    const providers = [...(sso?.providers ?? []).filter(provider => linkedKeys.has(provider.key)), ...organizationProviders];

    const reauthenticate = async (provider: SsoProvider) => {
        try {
            await startSso.mutateAsync({provider, intent: 'REAUTH', returnPath: resumeUrl});
        } catch (requestError) {
            identity.setError(ssoError(requestError).message);
            throw requestError;
        }
    };

    return (
        <div className={classes.reauth}>
            {showHint && <p className={classes.hint}>{t`For your security, sign in again to confirm it's you.`}</p>}
            <SsoButtons providers={providers} onStart={reauthenticate}/>
            {identity.error && <p className={classes.error} role="alert">{identity.error}</p>}
        </div>
    );
};
