import {useEffect, useRef, useState} from "react";
import {useQueryClient} from "@tanstack/react-query";
import {Badge, Button} from "@mantine/core";
import {t} from "@lingui/macro";
import {IconAlertTriangle, IconCircleCheck, IconExternalLink} from "@tabler/icons-react";
import {useStartSsoTest} from "../../mutations/useAccountSsoMutations.ts";
import {GET_ACCOUNT_SSO_QUERY_KEY} from "../../queries/useGetAccountSso.ts";
import {AccountSsoConnection, IdParam, SsoTestResult} from "../../../../types.ts";
import {relativeDate} from "../../../../utilites/dates.ts";
import {ssoError} from "../../../../utilites/ssoErrors.ts";
import {redirectToSso, SSO_TEST_MESSAGE, storeSsoFlow} from "../../../../utilites/ssoFlow.ts";
import {Callout} from "../../../../components/common/Callout";
import {connectionProviderName} from "./providers.tsx";
import classes from "./SingleSignOn.module.scss";

export const SSO_SETTINGS_RETURN_PATH = '/settings/account?sso=manage';

export type SsoTestOutcome = { test: SsoTestResult } | { error: string };

interface TestSignInProps {
    accountId: IdParam;
    connection: AccountSsoConnection;
    initialOutcome?: SsoTestOutcome | null;
}

export const TestSignIn = ({accountId, connection, initialOutcome = null}: TestSignInProps) => {
    const queryClient = useQueryClient();
    const startTest = useStartSsoTest();
    const [outcome, setOutcome] = useState<SsoTestOutcome | null>(initialOutcome);
    const [waiting, setWaiting] = useState(false);
    const popupRef = useRef<Window | null>(null);
    const providerName = connectionProviderName(connection.type);

    useEffect(() => {
        const receive = (event: MessageEvent) => {
            if (event.origin !== window.location.origin || event.data?.type !== SSO_TEST_MESSAGE) {
                return;
            }

            setWaiting(false);
            setOutcome(event.data.test ? {test: event.data.test} : {error: event.data.error ?? t`The test sign-in didn't finish.`});
            queryClient.invalidateQueries({queryKey: [GET_ACCOUNT_SSO_QUERY_KEY]});
        };

        window.addEventListener('message', receive);

        return () => window.removeEventListener('message', receive);
    }, []);

    useEffect(() => {
        if (!waiting) {
            return;
        }

        const timer = window.setInterval(() => {
            if (popupRef.current?.closed) {
                setWaiting(false);
                queryClient.invalidateQueries({queryKey: [GET_ACCOUNT_SSO_QUERY_KEY]});
            }
        }, 1000);

        return () => window.clearInterval(timer);
    }, [waiting]);

    const start = async () => {
        const usePopup = window.matchMedia('(min-width: 576px)').matches;
        const popup = usePopup ? window.open('', 'hi-sso-test', 'popup,width=520,height=720') : null;
        const flow = {
            provider: connection.provider_key,
            providerName,
            intent: 'TEST' as const,
            returnPath: SSO_SETTINGS_RETURN_PATH,
        };

        setOutcome(null);

        try {
            const response = await startTest.mutateAsync({accountId, returnPath: SSO_SETTINGS_RETURN_PATH});

            if (!popup) {
                redirectToSso(response, flow);
                return;
            }

            storeSsoFlow(response, flow, true);
            popup.location.href = response.authorization_url;
            popupRef.current = popup;
            setWaiting(true);
        } catch (error) {
            popup?.close();
            setOutcome({error: ssoError(error).message});
        }
    };

    return (
        <div className={classes.testSignIn}>
            <div className={classes.inlineRow}>
                <Button
                    variant="default"
                    leftSection={<IconExternalLink size={16}/>}
                    onClick={start}
                    loading={startTest.isPending}
                    data-testid="sso-test-sign-in"
                >
                    {t`Test sign-in`}
                </Button>
                <span className={classes.muted} aria-live="polite">
                    {waiting
                        ? t`Finish signing in to ${providerName} in the window that opened.`
                        : connection.last_tested_at ? t`Last test passed ${relativeDate(connection.last_tested_at)}` : t`Not tested yet`}
                </span>
            </div>

            {outcome && 'error' in outcome && (
                <Callout variant="danger" title={t`The test sign-in failed`}>{outcome.error}</Callout>
            )}

            {outcome && 'test' in outcome && (
                <div className={classes.testResult} data-testid="sso-test-result">
                    <div className={classes.testResultHeader}>
                        <IconCircleCheck size={18} className={classes.successIcon}/>
                        <span>{outcome.test.email ? t`Signed in as ${outcome.test.email}` : t`Signed in, but no email was shared`}</span>
                    </div>
                    <dl className={classes.testFacts}>
                        <dt>{t`Name`}</dt>
                        <dd>{[outcome.test.first_name, outcome.test.last_name].filter(Boolean).join(' ') || t`Not shared`}</dd>
                        <dt>{t`Email`}</dt>
                        <dd>
                            {outcome.test.email_verified
                                ? <Badge size="sm" variant="light" color="green">{t`Verified by ${providerName}`}</Badge>
                                : <Badge size="sm" variant="light" color="orange" leftSection={<IconAlertTriangle size={12}/>}>{t`Not marked as verified`}</Badge>}
                            {' '}
                            {outcome.test.email_domain_verified
                                ? <Badge size="sm" variant="light" color="green">{t`Domain verified`}</Badge>
                                : <Badge size="sm" variant="light" color="gray">{t`Domain not verified yet`}</Badge>}
                        </dd>
                        <dt>{t`Groups`}</dt>
                        <dd>{outcome.test.groups.length ? outcome.test.groups.join(', ') : t`None shared`}</dd>
                    </dl>
                    {!outcome.test.email_verified && (
                        <p className={classes.muted}>
                            {t`People can only join or be matched to existing accounts when ${providerName} marks their email as verified.`}
                        </p>
                    )}
                </div>
            )}
        </div>
    );
};
