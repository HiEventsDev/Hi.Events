import {Button, Radio, Select, TextInput} from "@mantine/core";
import {useForm} from "@mantine/form";
import {t} from "@lingui/macro";
import {IconCheck, IconCopy} from "@tabler/icons-react";
import dayjs from "dayjs";
import {FormEvent, useState} from "react";
import classNames from "classnames";
import {Modal, ModalFooter} from "../../../common/Modal";
import {Callout} from "../../../common/Callout";
import {ConfirmIdentityField, useIdentityConfirmation} from "../../../common/ConfirmIdentity";
import {useCreateApiKey} from "../../../../mutations/useApiKeyMutations.ts";
import {resumeUrl} from "../../../../hooks/useResumeAction.ts";
import {confirmationDialog} from "../../../../utilites/confirmationDialog.tsx";
import {formatDateWithLocale} from "../../../../utilites/dates.ts";
import {safeSessionStorageGet, safeSessionStorageRemove, safeSessionStorageSet} from "../../../../utilites/helpers.ts";
import {showError} from "../../../../utilites/notifications.tsx";
import {ApiKeyAccess, CreatedApiKey} from "../../../../types.ts";
import {API_KEYS_SETTINGS_PATH, CREATE_API_KEY_RESUME_ACTION} from "./constants.ts";
import {useApiBaseUrl} from "./useApiBaseUrl.ts";
import {CodeSnippet} from "./CodeSnippet.tsx";
import classes from "./ApiKeys.module.scss";

const DRAFT_STORAGE_KEY = 'hi-api-key-draft';
const NEVER = 'never';
const DEFAULT_EXPIRY_DAYS = 90;
const EXPIRY_REMINDER_MINIMUM_LIFETIME_DAYS = 14;

interface Draft {
    name: string;
    access: ApiKeyAccess;
    expires: string;
}

const readDraft = (): Partial<Draft> => {
    try {
        return JSON.parse(safeSessionStorageGet(DRAFT_STORAGE_KEY) ?? '{}');
    } catch {
        return {};
    }
};

const expiryLabel = (days: number) => {
    if (days === 365) {
        return t`1 year`;
    }

    return days === 1 ? t`1 day` : t`${days} days`;
};

interface CreateApiKeyModalProps {
    canEdit: boolean;
    accountName?: string;
    expiryOptions: number[];
    timezone: string;
    locale?: string;
    restoreDraft: boolean;
    onClose: () => void;
}

export const CreateApiKeyModal = ({canEdit, accountName, expiryOptions, timezone, locale, restoreDraft, onClose}: CreateApiKeyModalProps) => {
    const identity = useIdentityConfirmation();
    const createApiKey = useCreateApiKey();
    const apiBaseUrl = useApiBaseUrl();
    const [created, setCreated] = useState<CreatedApiKey | null>(null);
    const [formError, setFormError] = useState<string | null>(null);
    const [copyState, setCopyState] = useState<'idle' | 'copied' | 'failed'>('idle');
    const copied = copyState === 'copied';

    const [draft] = useState<Partial<Draft>>(() => restoreDraft ? readDraft() : {});

    const form = useForm<Draft>({
        initialValues: {
            name: draft.name ?? '',
            access: canEdit ? (draft.access ?? 'READ_WRITE') : 'READ_ONLY',
            expires: draft.expires ?? String(DEFAULT_EXPIRY_DAYS),
        },
        validate: {
            name: (value) => value.trim().length === 0 ? t`Give this key a name` : null,
        },
        onValuesChange: (values) => safeSessionStorageSet(DRAFT_STORAGE_KEY, JSON.stringify(values)),
    });

    const expiresInDays = form.values.expires === NEVER ? null : Number(form.values.expires);
    const expiryDate = expiresInDays === null ? null : dayjs.utc().add(expiresInDays, 'day').toISOString();
    const getsExpiryReminder = expiresInDays !== null && expiresInDays >= EXPIRY_REMINDER_MINIMUM_LIFETIME_DAYS;

    const closeAndForgetDraft = () => {
        safeSessionStorageRemove(DRAFT_STORAGE_KEY);
        onClose();
    };

    const submit = (event?: FormEvent) => {
        event?.preventDefault();

        if (form.validate().hasErrors || !identity.confirmation) {
            return;
        }

        setFormError(null);

        createApiKey.mutate({
            ...identity.confirmation,
            name: form.values.name.trim(),
            access: form.values.access,
            expires_in_days: expiresInDays,
        }, {
            onSuccess: (apiKey) => {
                safeSessionStorageRemove(DRAFT_STORAGE_KEY);
                setCreated(apiKey);
            },
            onError: (error: any) => {
                const data = error?.response?.data;
                const errors = data?.errors ?? {};

                identity.setError(errors.password?.[0] ?? null);
                form.setErrors({
                    name: errors.name?.[0],
                    access: errors.access?.[0],
                    expires: errors.expires_in_days?.[0],
                });

                if (Object.keys(errors).length === 0) {
                    const message = data?.message ?? t`Something went wrong. Please try again.`;

                    if (error?.response?.status >= 500 || !error?.response) {
                        showError(message);
                    } else {
                        setFormError(message);
                    }
                }
            },
        });
    };

    const copyKey = async () => {
        if (!created) {
            return;
        }

        try {
            await navigator.clipboard.writeText(created.key);
            setCopyState('copied');
        } catch {
            setCopyState('failed');
        }
    };

    const finish = () => {
        if (copied) {
            onClose();
            return;
        }

        confirmationDialog(
            t`You haven't copied this key. Once you close this window it can't be shown again.`,
            onClose,
            {confirm: t`Close without copying`, cancel: t`Go back`, destructive: true},
        );
    };

    if (created) {
        return (
            <Modal key="reveal" opened onClose={finish} heading={t`Copy your API key`} size={560}>
                <div className={classes.modalBody}>
                    <Callout variant="warning" title={t`This is the only time you'll see this key`}>
                        {t`Store it somewhere safe, like a password manager or your integration's secret settings. Anyone with this key can use the API as you.`}
                    </Callout>

                    <div>
                        <div className={classes.fieldLabel}>{created.name}</div>
                        <div className={classes.secret}>
                            <code
                                className={classes.secretValue}
                                onCopy={() => setCopyState('copied')}
                                data-testid="api-key-secret"
                            >
                                {created.key}
                            </code>
                            <Button
                                className={classes.secretCopy}
                                variant={copied ? 'light' : 'filled'}
                                color={copied ? 'teal' : undefined}
                                leftSection={copied ? <IconCheck size={16}/> : <IconCopy size={16}/>}
                                onClick={copyKey}
                                data-autofocus
                                data-testid="api-key-copy"
                            >
                                {copied ? t`Copied` : t`Copy key`}
                            </Button>
                        </div>
                        {copyState === 'failed' && (
                            <p className={classes.copyFailed} role="alert">
                                {t`Your browser blocked copying. Select the key and copy it yourself.`}
                            </p>
                        )}
                    </div>

                    <div>
                        <div className={classes.fieldLabel}>{t`Try it`}</div>
                        <CodeSnippet
                            code={`curl ${apiBaseUrl}/users/me \\\n  -H "Authorization: Bearer ${created.key}" \\\n  -H "Accept: application/json"`}
                            onCopy={() => setCopyState('copied')}
                        />
                    </div>

                    <ModalFooter>
                        <Button variant={copied ? 'filled' : 'default'} onClick={finish} data-testid="api-key-done">{t`Done`}</Button>
                    </ModalFooter>
                </div>
            </Modal>
        );
    }

    return (
        <Modal key="form" opened onClose={closeAndForgetDraft} heading={t`Create API key`} size={560}>
            <form className={classes.modalBody} onSubmit={submit} noValidate>
                {accountName && (
                    <p className={classes.modalText}>
                        {t`The key can do what you can do in ${accountName}, so keep it secret.`}
                    </p>
                )}

                {formError && <Callout variant="danger">{formError}</Callout>}

                <TextInput
                    label={t`Name`}
                    description={t`Something that tells you where it's used, like "Zapier" or "Website sync".`}
                    placeholder={t`e.g. Zapier`}
                    maxLength={60}
                    required
                    data-autofocus
                    data-testid="api-key-name"
                    {...form.getInputProps('name')}
                />

                <Radio.Group
                    label={t`Access`}
                    error={form.errors.access}
                    value={form.values.access}
                    onChange={(value) => form.setFieldValue('access', value as ApiKeyAccess)}
                >
                    <div className={classes.choices}>
                        <Radio.Card
                            value="READ_WRITE"
                            disabled={!canEdit}
                            className={classes.choice}
                            data-testid="api-key-access-read-write"
                        >
                            <span className={classes.choiceHead}>
                                <span className={classes.choiceLabel}>{t`Read and write`}</span>
                                <Radio.Indicator size="xs"/>
                            </span>
                            <span className={classes.choiceDescription}>
                                {canEdit
                                    ? t`Create and change events, orders, attendees and more.`
                                    : t`Not available: you have view-only access.`}
                            </span>
                        </Radio.Card>
                        <Radio.Card value="READ_ONLY" className={classes.choice} data-testid="api-key-access-read-only">
                            <span className={classes.choiceHead}>
                                <span className={classes.choiceLabel}>{t`Read-only`}</span>
                                <Radio.Indicator size="xs"/>
                            </span>
                            <span className={classes.choiceDescription}>
                                {t`View data and run exports. Can't change anything.`}
                            </span>
                        </Radio.Card>
                    </div>
                </Radio.Group>

                <div>
                    <Select
                        label={t`Expiration`}
                        data={[
                            ...expiryOptions.map(days => ({value: String(days), label: expiryLabel(days)})),
                            {value: NEVER, label: t`No expiration`},
                        ]}
                        allowDeselect={false}
                        error={form.errors.expires}
                        value={form.values.expires}
                        onChange={(value) => value && form.setFieldValue('expires', value)}
                        data-testid="api-key-expiry"
                    />
                    <div className={classNames(classes.expiryNote, {[classes.expiryWarning]: !expiryDate})}>
                        {!expiryDate
                            ? t`Keys that never expire stay valid until you revoke them. Only use this for servers you control.`
                            : getsExpiryReminder
                                ? t`Stops working on ${formatDateWithLocale(expiryDate, 'shortDate', timezone, locale)}. We'll email you a week before.`
                                : t`Stops working on ${formatDateWithLocale(expiryDate, 'shortDate', timezone, locale)}.`}
                    </div>
                </div>

                <ConfirmIdentityField
                    identity={identity}
                    resumeUrl={resumeUrl(API_KEYS_SETTINGS_PATH, CREATE_API_KEY_RESUME_ACTION)}
                    passwordTestId="api-key-password"
                />

                <ModalFooter>
                    <Button variant="default" onClick={closeAndForgetDraft}>{t`Cancel`}</Button>
                    <Button
                        type="submit"
                        loading={createApiKey.isPending}
                        disabled={!identity.confirmation}
                        data-testid="api-key-create-submit"
                    >
                        {t`Create key`}
                    </Button>
                </ModalFooter>
            </form>
        </Modal>
    );
};
