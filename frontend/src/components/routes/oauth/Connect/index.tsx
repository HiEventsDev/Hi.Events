import {Badge, Button, Loader, Radio} from "@mantine/core";
import {t} from "@lingui/macro";
import {IconAlertTriangle, IconPlugConnected, IconShieldCheck} from "@tabler/icons-react";
import {useState} from "react";
import {useSearchParams} from "react-router";
import {useAccess} from "../../../../hooks/useAccess.ts";
import {useApproveOAuthRequest, useDenyOAuthRequest} from "../../../../mutations/useOAuthMutations.ts";
import {useGetOAuthAuthorizationRequest} from "../../../../queries/useGetOAuthAuthorizationRequest.ts";
import {ApiKeyAccess, OAuthConsent} from "../../../../types.ts";
import {AuthShell} from "../../../layouts/AuthLayout/AuthShell.tsx";
import {Callout} from "../../../common/Callout";
import {ConfirmIdentityField, useIdentityConfirmation} from "../../../common/ConfirmIdentity";
import loginClasses from "../../auth/Login/Login.module.scss";
import classes from "./Connect.module.scss";

const REQUEST_ID_PATTERN = /^[A-Za-z0-9]{40}$/;

const destinationLabel = (consent: OAuthConsent) => consent.is_local ? t`an app on this computer` : consent.redirect_host;

const ConnectError = ({expired}: { expired: boolean }) => (
    <div className={loginClasses.challenge} data-testid="oauth-connect-error">
        <header className={loginClasses.challengeHeader}>
            <div className={`${loginClasses.challengeIcon} ${loginClasses.challengeIconWarning}`}>
                <IconAlertTriangle size={26} stroke={1.75}/>
            </div>
            <h2>{expired ? t`This connection request has expired` : t`This connection link doesn't work`}</h2>
            <p>{t`Go back to the app you were connecting and start again.`}</p>
        </header>
    </div>
);

const ConnectForm = ({consent, requestId}: { consent: OAuthConsent, requestId: string }) => {
    const {can} = useAccess();
    const identity = useIdentityConfirmation();
    const approve = useApproveOAuthRequest();
    const deny = useDenyOAuthRequest();
    const [leavingTo, setLeavingTo] = useState<string | null>(null);
    const [formError, setFormError] = useState<string | null>(null);
    const canWrite = can('EDIT') && consent.requested_access === 'READ_WRITE';
    const [chosenAccess, setAccess] = useState<ApiKeyAccess | null>(null);
    const access: ApiKeyAccess = canWrite ? (chosenAccess ?? 'READ_WRITE') : 'READ_ONLY';
    const destination = destinationLabel(consent);

    const leave = (url: string) => {
        setLeavingTo(destination);
        window.location.assign(url);
    };

    const onApprove = () => {
        if (!identity.confirmation) {
            return;
        }

        setFormError(null);
        approve.mutate({requestId, request: {...identity.confirmation, access}}, {
            onSuccess: (result) => leave(result.redirect_url),
            onError: (error: any) => {
                const passwordError = error?.response?.data?.errors?.password?.[0];

                if (passwordError) {
                    identity.setError(passwordError);
                    return;
                }

                setFormError(error?.response?.data?.message ?? t`Something went wrong. Please try again.`);
            },
        });
    };

    const onDeny = () => deny.mutate(requestId, {
        onSuccess: (result) => leave(result.redirect_url),
        onError: (error: any) => setFormError(error?.response?.data?.message ?? t`Something went wrong. Please try again.`),
    });

    if (leavingTo) {
        return (
            <div className={classes.leaving} role="status">
                <Loader size="sm"/>
                {t`Returning you to ${leavingTo}…`}
            </div>
        );
    }

    return (
        <div className={loginClasses.challenge} data-testid="oauth-connect">
            <header className={loginClasses.challengeHeader}>
                <div className={loginClasses.challengeIcon}><IconPlugConnected size={26} stroke={1.75}/></div>
                <h2>{t`Connect ${consent.client_name}`}</h2>
                <p>{t`${consent.client_name} wants to use your Hi.Events account ${consent.account_name}. It will act as you, with the access you choose below.`}</p>
            </header>

            <div className={classes.details}>
                <div className={classes.detail}>
                    <span className={classes.detailLabel}>{t`Returns you to`}</span>
                    <span className={classes.detailValue}>
                        {destination}
                        {consent.is_verified && (
                            <Badge size="xs" variant="light" color="green" leftSection={<IconShieldCheck size={12}/>}>
                                {t`Verified`}
                            </Badge>
                        )}
                    </span>
                </div>
            </div>

            {!consent.is_verified && (
                <Callout variant="warning">
                    {consent.is_local
                        ? t`Only continue if you just started connecting from an app on this computer, such as Claude Code or Cursor.`
                        : t`Hi.Events hasn't verified this app. Only continue if you started connecting it yourself and you trust it.`}
                </Callout>
            )}

            <Radio.Group
                value={access}
                onChange={(value) => setAccess(value as ApiKeyAccess)}
                label={t`Access`}
                className={classes.access}
            >
                <Radio
                    value="READ_WRITE"
                    disabled={!canWrite}
                    label={t`Read and write`}
                    description={t`Create and change events, tickets, questions and settings.`}
                    data-testid="oauth-connect-access-write"
                />
                <Radio
                    value="READ_ONLY"
                    label={t`Read only`}
                    description={t`View events, tickets and sales, without changing anything.`}
                    data-testid="oauth-connect-access-read"
                />
            </Radio.Group>

            <ConfirmIdentityField
                identity={identity}
                resumeUrl={`/connect?request=${encodeURIComponent(requestId)}`}
                passwordTestId="oauth-connect-password"
                autoFocus
            />

            {formError && <p className={classes.error} role="alert">{formError}</p>}

            <div className={classes.actions}>
                <Button
                    color="secondary.5"
                    fullWidth
                    onClick={onApprove}
                    loading={approve.isPending}
                    disabled={!identity.confirmation || deny.isPending}
                    data-testid="oauth-connect-approve"
                >
                    {t`Connect ${consent.client_name}`}
                </Button>
            </div>
            <Button
                variant="subtle"
                color="gray"
                fullWidth
                className={classes.cancel}
                onClick={onDeny}
                loading={deny.isPending}
                disabled={approve.isPending}
                data-testid="oauth-connect-deny"
            >
                {t`Cancel`}
            </Button>

            <p className={classes.footnote}>{t`You can disconnect it at any time from Settings → API keys.`}</p>
        </div>
    );
};

const Connect = () => {
    const [params] = useSearchParams();
    const requestParam = params.get('request');
    const requestId = requestParam && REQUEST_ID_PATTERN.test(requestParam) ? requestParam : null;
    const hasError = params.has('error') || !requestId;
    const {data: consent, isLoading, isError} = useGetOAuthAuthorizationRequest(hasError ? null : requestId);

    return (
        <AuthShell isStatic>
            {hasError && <ConnectError expired={false}/>}
            {!hasError && isLoading && <div className={classes.leaving}><Loader size="sm"/></div>}
            {!hasError && isError && <ConnectError expired/>}
            {!hasError && consent && requestId && <ConnectForm consent={consent} requestId={requestId}/>}
        </AuthShell>
    );
};

export default Connect;
