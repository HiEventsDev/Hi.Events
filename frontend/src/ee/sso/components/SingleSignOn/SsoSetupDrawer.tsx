import {useState} from "react";
import {Button, UnstyledButton} from "@mantine/core";
import {t} from "@lingui/macro";
import {IconPencil, IconTrash} from "@tabler/icons-react";
import {useDeleteSsoConnection} from "../../mutations/useAccountSsoMutations.ts";
import {AccountSso, AccountSsoConnectionType, IdParam} from "../../../../types.ts";
import {confirmationDialog} from "../../../../utilites/confirmationDialog.tsx";
import {showError, showSuccess} from "../../../../utilites/notifications.tsx";
import {SideDrawer, SideDrawerFields, SideDrawerHeading} from "../../../../components/common/SideDrawer";
import {AccessSettings} from "./AccessSettings.tsx";
import {ConnectionForm} from "./ConnectionForm.tsx";
import {DomainList} from "./DomainList.tsx";
import {EnforcementPanel} from "./EnforcementPanel.tsx";
import {ConnectionMark, connectionTypeDescription, connectionTypeLabel, connectionTypes} from "./providers.tsx";
import {SsoStep} from "./SsoStep.tsx";
import {SsoTestOutcome, TestSignIn} from "./TestSignIn.tsx";
import {LicenceLockedCallout} from "../../../licensing/components/LicenceLockedCallout";
import classes from "./SingleSignOn.module.scss";

interface SsoSetupDrawerProps {
    opened: boolean;
    onClose: () => void;
    accountId: IdParam;
    sso: AccountSso;
    testOutcome: SsoTestOutcome | null;
    setupLocked: boolean;
}

export const SsoSetupDrawer = ({opened, onClose, accountId, sso, testOutcome, setupLocked}: SsoSetupDrawerProps) => {
    const connection = sso.connection;
    const [chosenType, setChosenType] = useState<AccountSsoConnectionType | null>(null);
    const [editing, setEditing] = useState(false);
    const deleteMutation = useDeleteSsoConnection();
    const type = chosenType ?? connection?.type ?? null;
    const hasVerifiedDomain = sso.domains.some(domain => domain.status === 'VERIFIED' || domain.status === 'FAILING');
    const showForm = type !== null && (connection === null || editing);

    const close = () => {
        setChosenType(null);
        setEditing(false);
        onClose();
    };

    const remove = () => {
        confirmationDialog(
            t`Delete this connection? Members who only sign in with it will need to reset their password.`,
            () => deleteMutation.mutate(accountId, {
                onSuccess: () => {
                    setChosenType(null);
                    setEditing(false);
                    showSuccess(t`Connection deleted`);
                },
                onError: (error: any) => showError(error?.response?.data?.errors?.connection?.[0] ?? t`Something went wrong. Please try again.`),
            }),
            {confirm: t`Delete connection`, destructive: true},
        );
    };

    return (
        <SideDrawer
            opened={opened}
            onClose={close}
            header={(
                <SideDrawerHeading
                    media={type ? <span className={classes.headingMark}><ConnectionMark type={type} size={22}/></span> : undefined}
                    title={t`Single sign-on`}
                    subtitle={connection
                        ? `${connectionTypeLabel(connection.type)} · ${connection.status === 'ACTIVE' ? t`On` : t`Off`}`
                        : t`Let your team sign in with your identity provider`}
                />
            )}
        >
            {setupLocked && <LicenceLockedCallout/>}

            <div className={classes.steps}>
                <SsoStep number={1} title={t`Choose your identity provider`} done={connection !== null}>
                    {connection === null ? (
                        <div className={classes.providerCards} role="list">
                            {connectionTypes.map(option => (
                                <UnstyledButton
                                    key={option}
                                    role="listitem"
                                    className={classes.providerCard}
                                    data-selected={type === option || undefined}
                                    aria-pressed={type === option}
                                    onClick={() => setChosenType(option)}
                                    data-testid={`sso-provider-card-${option}`}
                                >
                                    <span className={classes.providerCardMark}><ConnectionMark type={option} size={22}/></span>
                                    <span className={classes.providerCardText}>
                                        <span className={classes.providerCardName}>{connectionTypeLabel(option)}</span>
                                        <span className={classes.muted}>{connectionTypeDescription(option)}</span>
                                    </span>
                                </UnstyledButton>
                            ))}
                        </div>
                    ) : (
                        <div className={classes.muted}>{connectionTypeLabel(connection.type)}</div>
                    )}
                </SsoStep>

                <SsoStep number={2} title={t`Connect your app`} done={connection !== null && !editing} locked={type === null}>
                    {showForm && type ? (
                        <ConnectionForm
                            key={type}
                            accountId={accountId}
                            type={type}
                            connection={connection}
                            redirectUri={sso.redirect_uri}
                            onSaved={() => {
                                setEditing(false);
                                setChosenType(null);
                            }}
                            onCancel={connection ? () => setEditing(false) : undefined}
                        />
                    ) : connection && (
                        <div className={classes.summary}>
                            <SideDrawerFields fields={[
                                connection.type === 'ENTRA'
                                    ? {label: t`Tenant ID`, value: connection.tenant_id}
                                    : connection.type === 'GOOGLE_WORKSPACE'
                                        ? {label: t`Workspace domain`, value: connection.hosted_domain}
                                        : {label: t`Issuer`, value: connection.issuer},
                                {label: t`Client ID`, value: connection.client_id},
                                {label: t`Client secret`, value: t`Saved`},
                            ]}/>
                            {!setupLocked && (
                                <Button variant="subtle" size="compact-sm" leftSection={<IconPencil size={14}/>} onClick={() => setEditing(true)}>
                                    {t`Edit details`}
                                </Button>
                            )}
                        </div>
                    )}
                </SsoStep>

                <SsoStep
                    number={3}
                    title={t`Test sign-in`}
                    description={t`Sign in once to check the connection. It shows what your identity provider shares and doesn't sign you in here.`}
                    done={!!connection?.last_tested_at}
                    locked={!connection || editing || setupLocked}
                >
                    {connection && !setupLocked && <TestSignIn accountId={accountId} connection={connection} initialOutcome={testOutcome}/>}
                </SsoStep>

                <SsoStep
                    number={4}
                    title={t`Verify your email domains`}
                    description={t`Only people with an email on a verified domain can be added automatically or required to use single sign-on.`}
                    done={hasVerifiedDomain}
                    locked={!connection}
                >
                    <DomainList accountId={accountId} domains={sso.domains} enforcing={connection?.enforce_sso === true} setupLocked={setupLocked}/>
                </SsoStep>

                <SsoStep number={5} title={t`Sign-in settings`} done={connection?.status === 'ACTIVE'} locked={!connection || editing}>
                    {connection && <AccessSettings accountId={accountId} connection={connection} setupLocked={setupLocked}/>}
                </SsoStep>

                <SsoStep number={6} title={t`Choose who must use it`} done={!!connection?.enforce_sso} locked={!connection || editing}>
                    {connection && <EnforcementPanel accountId={accountId} connection={connection} hasVerifiedDomain={hasVerifiedDomain} setupLocked={setupLocked}/>}
                </SsoStep>
            </div>

            {connection && (
                <div className={classes.dangerZone}>
                    <Button
                        variant="subtle"
                        color="red"
                        leftSection={<IconTrash size={16}/>}
                        onClick={remove}
                        disabled={connection.enforce_sso}
                    >
                        {t`Delete connection`}
                    </Button>
                    {connection.enforce_sso && <span className={classes.muted}>{t`Stop requiring single sign-on before deleting the connection.`}</span>}
                </div>
            )}
        </SideDrawer>
    );
};
