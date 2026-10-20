import {useState} from "react";
import {Badge, Button, Loader, Switch} from "@mantine/core";
import {t} from "@lingui/macro";
import {useGetMe} from "../../../../queries/useGetMe.ts";
import {useGetSsoEnforcementPreview} from "../../queries/useGetSsoEnforcementPreview.ts";
import {useSetSsoMemberEnforced, useUpdateSsoEnforcement} from "../../mutations/useAccountSsoMutations.ts";
import {AccountSsoConnection, IdParam, SsoEnforcementMember} from "../../../../types.ts";
import {confirmationDialog} from "../../../../utilites/confirmationDialog.tsx";
import {showError, showSuccess} from "../../../../utilites/notifications.tsx";
import {Callout} from "../../../../components/common/Callout";
import {connectionProviderName} from "./providers.tsx";
import classes from "./SingleSignOn.module.scss";

const memberName = (member: SsoEnforcementMember) => [member.first_name, member.last_name].filter(Boolean).join(' ') || member.email;

const errorMessage = (error: any): string => error?.response?.data?.errors?.enforce_sso?.[0]
    ?? error?.response?.data?.errors?.account_user_id?.[0]
    ?? error?.response?.data?.message
    ?? t`Something went wrong. Please try again.`;

interface EnforcementPanelProps {
    accountId: IdParam;
    connection: AccountSsoConnection;
    hasVerifiedDomain: boolean;
    setupLocked: boolean;
}

export const EnforcementPanel = ({accountId, connection, hasVerifiedDomain, setupLocked}: EnforcementPanelProps) => {
    const [previewing, setPreviewing] = useState(false);
    const [exempt, setExempt] = useState<IdParam[]>([]);
    const isEnforcing = connection.enforce_sso;
    const {data: me} = useGetMe();
    const {data: preview, isFetching} = useGetSsoEnforcementPreview(accountId, previewing || isEnforcing);
    const updateMutation = useUpdateSsoEnforcement();
    const memberMutation = useSetSsoMemberEnforced();
    const providerName = connectionProviderName(connection.type);
    const canEnable = !setupLocked && connection.status === 'ACTIVE' && hasVerifiedDomain;

    const onDomainMembers = (preview?.members ?? []).filter(member => member.on_verified_domain && !member.is_account_owner);
    const owner = preview?.members.find(member => member.is_account_owner);
    const willBeEnforced = (member: SsoEnforcementMember) => member.sso_available && !exempt.includes(member.account_user_id);
    const enforcedCount = onDomainMembers.filter(willBeEnforced).length;
    const meAffected = onDomainMembers.some(member => String(member.user_id) === String(me?.id) && willBeEnforced(member));
    const unavailableNote = t`Owns another Hi.Events account, so keeps signing in with a password.`;

    const toggleExempt = (member: SsoEnforcementMember, isExempt: boolean) => {
        setExempt(current => isExempt
            ? [...current, member.account_user_id]
            : current.filter(id => id !== member.account_user_id));
    };

    const enable = () => {
        updateMutation.mutate({accountId, enabled: true, exemptAccountUserIds: exempt}, {
            onSuccess: () => {
                setPreviewing(false);
                showSuccess(t`Single sign-on is now required`);
                if (meAffected) {
                    window.location.assign('/auth/login');
                }
            },
            onError: (error) => showError(errorMessage(error)),
        });
    };

    const disable = () => {
        confirmationDialog(
            t`Stop requiring single sign-on? Members can sign in with their password again, or reset it if they never had one.`,
            () => updateMutation.mutate({accountId, enabled: false}, {
                onSuccess: () => showSuccess(t`Single sign-on is no longer required`),
                onError: (error) => showError(errorMessage(error)),
            }),
            {confirm: t`Stop requiring`},
        );
    };

    const setMemberEnforced = (member: SsoEnforcementMember, enforced: boolean) => {
        memberMutation.mutate({accountId, accountUserId: member.account_user_id, enforced}, {
            onSuccess: () => showSuccess(enforced
                ? t`${memberName(member)} must now sign in with ${providerName}`
                : t`${memberName(member)} can sign in with their password`),
            onError: (error) => showError(errorMessage(error)),
        });
    };

    const onSwitch = (checked: boolean) => {
        if (isEnforcing && !checked) {
            disable();
            return;
        }

        setExempt([]);
        setPreviewing(checked);
    };

    return (
        <div className={classes.enforcement}>
            <div className={classes.settingRow}>
                <div className={classes.settingText}>
                    <div className={classes.settingLabel}>{t`Require single sign-on`}</div>
                    <div className={classes.muted}>
                        {canEnable || setupLocked
                            ? t`Members with an email on a verified domain must sign in with ${providerName}. Their other sessions end.`
                            : t`Turn on sign-in with ${providerName} and verify a domain first.`}
                    </div>
                </div>
                <Switch
                    size="md"
                    checked={isEnforcing || previewing}
                    disabled={(!canEnable && !isEnforcing) || updateMutation.isPending}
                    onChange={(event) => onSwitch(event.currentTarget.checked)}
                    aria-label={t`Require single sign-on`}
                    aria-expanded={previewing}
                    data-testid="sso-enforce-switch"
                />
            </div>

            {(previewing || isEnforcing) && isFetching && !preview && <Loader size="sm"/>}

            {previewing && preview && (
                <div className={classes.preview} data-testid="sso-enforcement-preview">
                    <p className={classes.previewSummary}>
                        {onDomainMembers.length === 0
                            ? t`Nobody on your team uses a verified domain yet. People who join from one will need to sign in with ${providerName}.`
                            : enforcedCount === 1 ? t`1 member will need to sign in with ${providerName}.` : t`${enforcedCount} members will need to sign in with ${providerName}.`}
                        {' '}
                        {preview.outside_domain_count === 1
                            ? t`1 member outside your verified domains keeps their current sign-in.`
                            : t`${preview.outside_domain_count} members outside your verified domains keep their current sign-in.`}
                    </p>

                    {onDomainMembers.length > 0 && (
                        <ul className={classes.memberList}>
                            {onDomainMembers.map(member => {
                                const isExempt = exempt.includes(member.account_user_id);

                                return (
                                    <li key={member.account_user_id} className={classes.memberRow}>
                                        <div className={classes.memberText}>
                                            <span className={classes.memberName}>{memberName(member)}</span>
                                            <span className={classes.muted}>{member.email}</span>
                                            {!member.sso_available && <span className={classes.muted}>{unavailableNote}</span>}
                                        </div>
                                        {member.sso_available && (
                                            <Switch
                                                size="sm"
                                                label={t`Exempt`}
                                                labelPosition="left"
                                                checked={isExempt}
                                                onChange={(event) => toggleExempt(member, event.currentTarget.checked)}
                                            />
                                        )}
                                    </li>
                                );
                            })}
                        </ul>
                    )}

                    {owner && (
                        <Callout variant="info">
                            {t`The account owner (${owner.email}) can still sign in with their password and two-factor authentication, so you can recover access if ${providerName} is unavailable. Every admin is emailed when that happens.`}
                        </Callout>
                    )}

                    {meAffected && (
                        <Callout variant="warning">
                            {t`You'll be signed out and will need to sign in again with ${providerName}.`}
                        </Callout>
                    )}

                    <div className={classes.previewActions}>
                        <Button variant="subtle" color="gray" onClick={() => setPreviewing(false)}>{t`Cancel`}</Button>
                        <Button onClick={enable} loading={updateMutation.isPending} data-testid="sso-enforce-confirm">
                            {t`Require single sign-on`}
                        </Button>
                    </div>
                </div>
            )}

            {isEnforcing && preview && onDomainMembers.length > 0 && (
                <ul className={classes.memberList} aria-label={t`Members on verified domains`}>
                    {onDomainMembers.map(member => (
                        <li key={member.account_user_id} className={classes.memberRow}>
                            <div className={classes.memberText}>
                                <span className={classes.memberName}>{memberName(member)}</span>
                                <span className={classes.muted}>{member.email}</span>
                                {!member.sso_available && <span className={classes.muted}>{unavailableNote}</span>}
                            </div>
                            {member.sso_enforced && member.sso_available
                                ? <Badge size="sm" variant="light" color="blue">{t`SSO`}</Badge>
                                : <Badge size="sm" variant="light" color="gray">{member.sso_available ? t`Exempt` : t`Password`}</Badge>}
                            {member.sso_available && (member.sso_enforced || !setupLocked) && <Button
                                size="compact-xs"
                                variant="subtle"
                                onClick={() => setMemberEnforced(member, !member.sso_enforced)}
                                loading={memberMutation.isPending && memberMutation.variables?.accountUserId === member.account_user_id}
                            >
                                {member.sso_enforced ? t`Exempt` : t`Require SSO`}
                            </Button>}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
};
