import {useState} from "react";
import {Button, Group, Loader, PasswordInput, TextInput} from "@mantine/core";
import {useForm} from "@mantine/form";
import {t} from "@lingui/macro";
import {IconCircleCheck} from "@tabler/icons-react";
import {useFormErrorResponseHandler} from "../../../../hooks/useFormErrorResponseHandler.tsx";
import {useCheckSsoConnection, useSaveSsoConnection} from "../../mutations/useAccountSsoMutations.ts";
import {AccountSsoConnection, AccountSsoConnectionRequest, AccountSsoConnectionType, IdParam} from "../../../../types.ts";
import {showSuccess} from "../../../../utilites/notifications.tsx";
import {CopyButton} from "../../../../components/common/CopyButton";
import {connectionTypeLabel, setupInstructions} from "./providers.tsx";
import classes from "./SingleSignOn.module.scss";

interface ConnectionFormProps {
    accountId: IdParam;
    type: AccountSsoConnectionType;
    connection: AccountSsoConnection | null;
    redirectUri: string;
    onSaved: () => void;
    onCancel?: () => void;
}

type CheckState = { status: 'idle' | 'checking' | 'valid' } | { status: 'invalid', message: string };

interface ConnectionFormValues {
    issuer: string;
    tenant_id: string;
    hosted_domain: string;
    client_id: string;
    client_secret: string;
}

export const connectionRequest = (connection: AccountSsoConnection, overrides: Partial<AccountSsoConnectionRequest> = {}): AccountSsoConnectionRequest => ({
    type: connection.type,
    client_id: connection.client_id,
    client_secret: null,
    issuer: connection.issuer,
    tenant_id: connection.tenant_id,
    hosted_domain: connection.hosted_domain,
    session_ttl_minutes: connection.session_ttl_minutes,
    jit_provisioning_enabled: connection.jit_provisioning_enabled,
    jit_default_role: connection.jit_default_role,
    ...overrides,
});

export const ConnectionForm = ({accountId, type, connection, redirectUri, onSaved, onCancel}: ConnectionFormProps) => {
    const saveMutation = useSaveSsoConnection();
    const checkMutation = useCheckSsoConnection();
    const formErrorHandler = useFormErrorResponseHandler();
    const [check, setCheck] = useState<CheckState>({status: 'idle'});
    const isNew = connection === null;
    const locationField = type === 'ENTRA' ? 'tenant_id' : type === 'GOOGLE_WORKSPACE' ? 'hosted_domain' : 'issuer';
    const usesIssuer = type === 'OKTA' || type === 'OIDC';

    const identityChanged = (values: ConnectionFormValues): boolean => connection !== null && (
        connection.type !== type
        || values.client_id.trim() !== connection.client_id
        || (usesIssuer && values.issuer.trim().replace(/\/+$/, '') !== connection.issuer)
        || (type === 'ENTRA' && values.tenant_id.trim().toLowerCase() !== (connection.tenant_id ?? ''))
        || (type === 'GOOGLE_WORKSPACE' && values.hosted_domain.trim().toLowerCase() !== (connection.hosted_domain ?? ''))
    );
    const secretRequired = (values: ConnectionFormValues): boolean => isNew || identityChanged(values);

    const form = useForm<ConnectionFormValues>({
        initialValues: {
            issuer: connection?.type === type && usesIssuer ? connection.issuer : '',
            tenant_id: connection?.tenant_id ?? '',
            hosted_domain: connection?.hosted_domain ?? '',
            client_id: connection?.type === type ? connection.client_id : '',
            client_secret: '',
        },
        validate: {
            issuer: (value) => usesIssuer && !value.trim() ? t`Enter the issuer URL, starting with https://.` : null,
            tenant_id: (value) => type === 'ENTRA' && !value.trim() ? t`Enter the directory (tenant) ID. It looks like 00000000-0000-0000-0000-000000000000.` : null,
            hosted_domain: (value) => type === 'GOOGLE_WORKSPACE' && !value.trim() ? t`Enter your Google Workspace domain, like example.com.` : null,
            client_id: (value) => !value.trim() ? t`Enter the client ID.` : null,
            client_secret: (value, values) => value.trim() || !secretRequired(values)
                ? null
                : isNew ? t`Enter the client secret.` : t`Enter the client secret again when you change the identity provider details.`,
        },
    });

    const request = (): AccountSsoConnectionRequest => ({
        type,
        client_id: form.values.client_id.trim(),
        client_secret: form.values.client_secret.trim() || null,
        issuer: form.values.issuer.trim() || null,
        tenant_id: form.values.tenant_id.trim() || null,
        hosted_domain: form.values.hosted_domain.trim() || null,
        session_ttl_minutes: connection?.session_ttl_minutes ?? 720,
        jit_provisioning_enabled: connection?.jit_provisioning_enabled ?? false,
        jit_default_role: connection?.jit_default_role ?? 'ORGANIZER',
    });

    const checkLocation = () => {
        if (!form.values[locationField].trim()) {
            setCheck({status: 'idle'});
            return;
        }

        const checkedValue = form.values[locationField];
        const isCurrent = () => form.getValues()[locationField] === checkedValue;

        setCheck({status: 'checking'});
        checkMutation.mutate({accountId, request: {...request(), client_id: request().client_id || 'pending'}}, {
            onSuccess: () => isCurrent() && setCheck({status: 'valid'}),
            onError: (error: any) => {
                if (!isCurrent()) {
                    return;
                }

                const errors = error?.response?.data?.errors ?? {};
                const message = errors[locationField]?.[0] ?? errors[locationField];

                setCheck(message ? {status: 'invalid', message: String(message)} : {status: 'idle'});
            },
        });
    };

    const submit = () => {
        saveMutation.mutate({accountId, request: request(), isNew}, {
            onSuccess: () => {
                showSuccess(isNew ? t`Connection saved. Run a test sign-in next.` : t`Connection updated`);
                form.resetDirty();
                onSaved();
            },
            onError: (error) => formErrorHandler(form, error),
        });
    };

    const locationProps = form.getInputProps(locationField);
    const locationHint = check.status === 'checking'
        ? <span className={classes.checkHint}><Loader size={12}/>{t`Checking…`}</span>
        : check.status === 'valid'
            ? <span className={classes.checkValid}><IconCircleCheck size={14}/>{t`Found your sign-in settings`}</span>
            : undefined;

    const locationInput = {
        ...locationProps,
        onBlur: () => {
            locationProps.onBlur?.();
            checkLocation();
        },
        onChange: (event: any) => {
            locationProps.onChange(event);
            setCheck({status: 'idle'});
        },
        error: locationProps.error ?? (check.status === 'invalid' ? check.message : undefined),
        description: locationHint,
    };

    return (
        <form onSubmit={form.onSubmit(submit)} noValidate>
            <ol className={classes.instructions}>
                {setupInstructions(type).map(instruction => <li key={instruction}>{instruction}</li>)}
            </ol>

            <div className={classes.redirectUri}>
                <div className={classes.redirectUriText}>
                    <div className={classes.redirectUriLabel}>{t`Redirect URI`}</div>
                    <code className={classes.redirectUriValue}>{redirectUri}</code>
                </div>
                <CopyButton value={redirectUri} label={t`Copy redirect URI`}/>
            </div>

            {type === 'ENTRA' && (
                <TextInput
                    {...locationInput}
                    label={t`Directory (tenant) ID`}
                    placeholder="00000000-0000-0000-0000-000000000000"
                    autoComplete="off"
                    required
                />
            )}
            {type === 'GOOGLE_WORKSPACE' && (
                <TextInput
                    {...locationInput}
                    label={t`Workspace domain`}
                    placeholder="example.com"
                    autoComplete="off"
                    required
                />
            )}
            {usesIssuer && (
                <TextInput
                    {...locationInput}
                    label={type === 'OKTA' ? t`Okta domain` : t`Issuer URL`}
                    placeholder={type === 'OKTA' ? 'https://your-org.okta.com' : 'https://sso.example.com/realms/staff'}
                    autoComplete="off"
                    inputMode="url"
                    required
                />
            )}
            <TextInput
                {...form.getInputProps('client_id')}
                label={type === 'ENTRA' ? t`Application (client) ID` : t`Client ID`}
                autoComplete="off"
                required
            />
            <PasswordInput
                {...form.getInputProps('client_secret')}
                label={t`Client secret`}
                description={isNew
                    ? undefined
                    : secretRequired(form.values)
                        ? t`You changed the provider details, so enter the client secret again.`
                        : t`Saved and hidden. Leave blank to keep the current secret.`}
                autoComplete="new-password"
                required={secretRequired(form.values)}
            />

            <Group justify="flex-end" gap="sm" mt="md">
                {onCancel && <Button variant="subtle" color="gray" onClick={onCancel}>{t`Cancel`}</Button>}
                <Button type="submit" loading={saveMutation.isPending} data-testid="sso-connection-save">
                    {isNew ? t`Save ${connectionTypeLabel(type)} connection` : t`Save changes`}
                </Button>
            </Group>
        </form>
    );
};
