import {useEffect, useState} from "react";
import {Button} from "@mantine/core";
import {t} from "@lingui/macro";
import {IconShieldLock} from "@tabler/icons-react";
import {useLocation, useNavigate} from "react-router";
import {useGetAccountSso} from "../../queries/useGetAccountSso.ts";
import {IdParam} from "../../../../types.ts";
import {SettingsSection} from "../../../../components/common/SettingsPage";
import {StatusDot} from "../../../../components/common/StatusDot";
import {connectionTypeLabel} from "./providers.tsx";
import {SsoSetupDrawer} from "./SsoSetupDrawer.tsx";
import {SsoTestOutcome} from "./TestSignIn.tsx";
import classes from "./SingleSignOn.module.scss";
import {LicenceLockedCallout} from "../../../licensing/components/LicenceLockedCallout";

export const SingleSignOnSection = ({accountId, setupLocked}: { accountId: IdParam, setupLocked: boolean }) => {
    const location = useLocation();
    const navigate = useNavigate();
    const {data: sso} = useGetAccountSso(accountId);
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [testOutcome, setTestOutcome] = useState<SsoTestOutcome | null>(null);

    useEffect(() => {
        const params = new URLSearchParams(location.search);
        const state = location.state as { ssoTest?: SsoTestOutcome } | null;

        if (!params.has('sso') && !state?.ssoTest) {
            return;
        }

        setTestOutcome(state?.ssoTest ?? null);
        setDrawerOpen(true);
        params.delete('sso');
        const query = params.toString();
        navigate(location.pathname + (query ? `?${query}` : '') + location.hash, {replace: true, state: null});
    }, []);

    if (!sso) {
        return null;
    }

    const connection = sso.connection;
    const connectionStatus = !connection
        ? null
        : connection.status === 'ACTIVE'
            ? <StatusDot tone="success" strong>{t`On`}</StatusDot>
            : <StatusDot tone="muted">{connection.last_tested_at ? t`Off` : t`Not tested`}</StatusDot>;

    return (
        <SettingsSection id="single-sign-on" title={t`Single sign-on`}>
            {setupLocked && <LicenceLockedCallout/>}

            {connection ? (
                <div className={classes.overview}>
                    <dl className={classes.statusStrip}>
                        <div className={classes.statusItem}>
                            <dt>{connectionTypeLabel(connection.type)}</dt>
                            <dd>{connectionStatus}</dd>
                        </div>
                        <div className={classes.statusItem}>
                            <dt>{t`Verified domains`}</dt>
                            <dd>{sso.stats.verified_domain_count}/{sso.stats.domain_count}</dd>
                        </div>
                        <div className={classes.statusItem}>
                            <dt>{t`SSO sign-ins (30 days)`}</dt>
                            <dd>{sso.stats.sso_sign_ins_last_30_days}</dd>
                        </div>
                        <div className={classes.statusItem}>
                            <dt>{t`Required`}</dt>
                            <dd>{connection.enforce_sso ? <StatusDot tone="success" strong>{t`Yes`}</StatusDot> : <StatusDot tone="muted">{t`No`}</StatusDot>}</dd>
                        </div>
                    </dl>
                    <div className={classes.overviewActions}>
                        <Button variant="default" onClick={() => setDrawerOpen(true)} data-testid="sso-manage-button">
                            {t`Manage single sign-on`}
                        </Button>
                    </div>
                </div>
            ) : (
                <div className={classes.emptyState}>
                    <div className={classes.emptyIcon}><IconShieldLock size={20} stroke={1.75}/></div>
                    <div className={classes.emptyText}>
                        <div className={classes.settingLabel}>{t`Let your team sign in with Okta, Microsoft Entra ID or Google Workspace`}</div>
                        <div className={classes.muted}>{t`Connect your identity provider, verify your email domains, then choose whether everyone must use it.`}</div>
                    </div>
                    {!setupLocked && <Button onClick={() => setDrawerOpen(true)} data-testid="sso-setup-button">{t`Set up SSO`}</Button>}
                </div>
            )}

            <SsoSetupDrawer
                opened={drawerOpen}
                onClose={() => {
                    setDrawerOpen(false);
                    setTestOutcome(null);
                }}
                accountId={accountId}
                sso={sso}
                testOutcome={testOutcome}
                setupLocked={setupLocked}
            />
        </SettingsSection>
    );
};
