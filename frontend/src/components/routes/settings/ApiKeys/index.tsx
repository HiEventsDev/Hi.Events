import {Anchor, Button, Group, SegmentedControl, Skeleton} from "@mantine/core";
import {t, Trans} from "@lingui/macro";
import {IconArrowRight, IconBook2, IconKey, IconPlus} from "@tabler/icons-react";
import {useEffect, useMemo, useState} from "react";
import {NavLink} from "react-router";
import {useGetApiKeys} from "../../../../queries/useGetApiKeys.ts";
import {useGetMe} from "../../../../queries/useGetMe.ts";
import {useGetAccount} from "../../../../queries/useGetAccount.ts";
import {useDeleteApiKey} from "../../../../mutations/useApiKeyMutations.ts";
import {useAccess} from "../../../../hooks/useAccess.ts";
import {useResumeAction} from "../../../../hooks/useResumeAction.ts";
import {confirmationDialog} from "../../../../utilites/confirmationDialog.tsx";
import {showError, showSuccess} from "../../../../utilites/notifications.tsx";
import {SettingsPage} from "../../../common/SettingsPage";
import {Callout} from "../../../common/Callout";
import {ApiKey} from "../../../../types.ts";
import {ApiKeyRow} from "./ApiKeyRow.tsx";
import {CreateApiKeyModal} from "./CreateApiKeyModal.tsx";
import {CodeSnippet} from "./CodeSnippet.tsx";
import {API_REFERENCE_PATH, CREATE_API_KEY_RESUME_ACTION} from "./constants.ts";
import {useApiBaseUrl} from "./useApiBaseUrl.ts";
import classes from "./ApiKeys.module.scss";

type Scope = 'mine' | 'everyone';

const ApiKeysSettings = () => {
    const {data, isLoading, isError, refetch} = useGetApiKeys();
    const {data: me} = useGetMe();
    const {data: account} = useGetAccount();
    const {can} = useAccess();
    const deleteApiKey = useDeleteApiKey();
    const resumeAction = useResumeAction();
    const apiBaseUrl = useApiBaseUrl();
    const [createOpen, setCreateOpen] = useState(false);
    const [resumed, setResumed] = useState(false);
    const [scope, setScope] = useState<Scope>('mine');

    const canManageTeam = can('MANAGE_TEAM');
    const timezone = me?.timezone ?? 'UTC';
    const locale = me?.locale;
    const meta = data?.meta;
    const keys = data?.data ?? [];

    useEffect(() => {
        if (resumeAction === CREATE_API_KEY_RESUME_ACTION) {
            setResumed(true);
            setCreateOpen(true);
        }
    }, [resumeAction]);

    const {active, expired, ownActiveCount} = useMemo(() => {
        const visible = canManageTeam && scope === 'everyone' ? keys : keys.filter(key => key.is_own);

        return {
            active: visible.filter(key => !key.is_expired),
            expired: visible.filter(key => key.is_expired),
            ownActiveCount: keys.filter(key => key.is_own && !key.is_expired).length,
        };
    }, [keys, scope, canManageTeam]);

    const isUnverified = me?.is_email_verified === false;
    const isImpersonating = me?.is_impersonating === true;
    const atLimit = !!meta && ownActiveCount >= meta.max_active_keys;

    const openCreate = () => {
        setResumed(false);
        setCreateOpen(true);
    };

    const revoke = (apiKey: ApiKey) => {
        const ownerName = apiKey.owner?.full_name;
        const message = apiKey.is_expired
            ? t`Remove "${apiKey.name}"? It has already expired.`
            : !apiKey.is_own && ownerName
                ? t`Revoke "${apiKey.name}"? Anything using it stops working straight away, and ${ownerName} will get an email about it.`
                : t`Revoke "${apiKey.name}"? Anything using it stops working straight away. This can't be undone.`;

        confirmationDialog(message, () => deleteApiKey.mutate(apiKey.id, {
            onSuccess: () => showSuccess(apiKey.is_expired ? t`Key removed` : t`Key revoked`),
            onError: (error: any) => showError(error?.response?.data?.message ?? t`Something went wrong. Please try again.`),
        }), {confirm: apiKey.is_expired ? t`Remove key` : t`Revoke key`, destructive: true});
    };

    const createButton = (
        <Button
            leftSection={<IconPlus size={16}/>}
            onClick={openCreate}
            disabled={!meta || isUnverified || isImpersonating || atLimit}
            data-testid="api-key-create-button"
        >
            {t`Create API key`}
        </Button>
    );

    const renderRows = (list: ApiKey[]) => list.map(apiKey => (
        <ApiKeyRow
            key={apiKey.id}
            apiKey={apiKey}
            timezone={timezone}
            locale={locale}
            showOwner={canManageTeam && scope === 'everyone'}
            onRevoke={() => revoke(apiKey)}
        />
    ));

    const isEmpty = !isLoading && !isError && active.length === 0 && expired.length === 0;

    return (
        <SettingsPage
            width="wide"
            title={t`API keys`}
            description={t`Connect other tools and your own code to this account. A key can do what you can do, so treat it like a password.`}
            actions={(
                <Group gap="xs">
                    <Button
                        component={NavLink}
                        to={API_REFERENCE_PATH}
                        variant="default"
                        leftSection={<IconBook2 size={16}/>}
                        data-testid="api-reference-link"
                    >
                        {t`API reference`}
                    </Button>
                    {!isLoading && !isEmpty && createButton}
                </Group>
            )}
        >
            <div className={classes.stack}>
                {isUnverified && (
                    <Callout variant="warning" title={t`Confirm your email address`}>
                        <Trans>
                            You need a confirmed email address to create API keys. Check your inbox, or resend the confirmation email from your <NavLink to="/settings/profile">profile</NavLink>.
                        </Trans>
                    </Callout>
                )}

                {isImpersonating && (
                    <Callout variant="info">
                        {t`API keys can't be created while you're impersonating someone.`}
                    </Callout>
                )}

                {!isUnverified && !isImpersonating && atLimit && meta && (
                    <Callout variant="info">
                        {t`You've reached the limit of ${meta.max_active_keys} active keys. Revoke one to create another.`}
                    </Callout>
                )}

                <section className={classes.panel} aria-busy={isLoading}>
                    {canManageTeam && (keys.length > 0 || scope === 'everyone') && (
                        <div className={classes.toolbar}>
                            <SegmentedControl
                                size="xs"
                                value={scope}
                                onChange={(value) => setScope(value as Scope)}
                                data={[
                                    {value: 'mine', label: t`My keys`},
                                    {value: 'everyone', label: t`Everyone (${keys.length})`},
                                ]}
                                aria-label={t`Show keys`}
                                data-testid="api-key-scope"
                            />
                            {meta && scope === 'mine' && (
                                <span className={classes.toolbarCount}>
                                    {t`${ownActiveCount} of ${meta.max_active_keys} active keys`}
                                </span>
                            )}
                        </div>
                    )}

                    {!isEmpty && !isError && (
                        <div className={classes.columns} aria-hidden>
                            <span>{t`Key`}</span>
                            <span>{t`Created`}</span>
                            <span>{t`Last used`}</span>
                            <span>{t`Expires`}</span>
                            <span/>
                        </div>
                    )}

                    {isLoading && [0, 1].map(index => (
                        <div className={classes.row} key={index}><Skeleton height={38} radius="md"/></div>
                    ))}

                    {isError && (
                        <div className={classes.empty}>
                            <div className={classes.emptyTitle}>{t`Couldn't load your API keys`}</div>
                            <Button variant="default" size="xs" onClick={() => refetch()}>{t`Try again`}</Button>
                        </div>
                    )}

                    {isEmpty && (
                        <div className={classes.empty} data-testid="api-keys-empty">
                            <div className={classes.emptyIcon}><IconKey size={22}/></div>
                            <div className={classes.emptyTitle}>
                                {scope === 'everyone' ? t`Nobody has created an API key yet` : t`You don't have any API keys`}
                            </div>
                            <p className={classes.emptyText}>
                                {t`Create a key to connect tools like Zapier, your CRM or your own scripts to your events, orders and attendees.`}
                            </p>
                            {createButton}
                        </div>
                    )}

                    {renderRows(active)}

                    {expired.length > 0 && (
                        <>
                            <div className={classes.groupLabel}>
                                {t`Expired`} <span className={classes.groupCount}>{expired.length}</span>
                            </div>
                            {renderRows(expired)}
                        </>
                    )}
                </section>

                <section className={classes.guide}>
                    <div>
                        <h2 className={classes.guideTitle}>{t`Using your key`}</h2>
                        <ul className={classes.guideList}>
                            <li>{t`Send it in the Authorization header of every request.`}</li>
                            <li>{t`It has your role and event access. Read-only keys can view and export, but can't change anything.`}</li>
                            <li>{t`Keys can't sign in or manage your team, payouts, profile or security settings.`}</li>
                            <li>{t`If a key leaks, revoke it straight away and create a new one.`}</li>
                        </ul>
                        <Anchor component={NavLink} to={API_REFERENCE_PATH} className={classes.guideLink}>
                            {t`Browse every endpoint in the API reference`} <IconArrowRight size={14}/>
                        </Anchor>
                    </div>
                    <CodeSnippet
                        code={`curl ${apiBaseUrl}/events \\\n  -H "Authorization: Bearer YOUR_API_KEY" \\\n  -H "Accept: application/json"`}
                    />
                </section>
            </div>

            {createOpen && meta && (
                <CreateApiKeyModal
                    canEdit={can('EDIT')}
                    accountName={account?.name}
                    expiryOptions={meta.expiry_options_in_days}
                    timezone={timezone}
                    locale={locale}
                    restoreDraft={resumed}
                    onClose={() => setCreateOpen(false)}
                />
            )}
        </SettingsPage>
    );
};

export default ApiKeysSettings;
