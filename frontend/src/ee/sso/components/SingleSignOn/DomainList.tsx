import {ActionIcon, Badge, Button, TextInput, Tooltip} from "@mantine/core";
import {useForm} from "@mantine/form";
import {t} from "@lingui/macro";
import {IconTrash} from "@tabler/icons-react";
import {useFormErrorResponseHandler} from "../../../../hooks/useFormErrorResponseHandler.tsx";
import {useAddSsoDomain, useDeleteSsoDomain, useVerifySsoDomain} from "../../mutations/useAccountSsoMutations.ts";
import {AccountSsoDomain, IdParam} from "../../../../types.ts";
import {confirmationDialog} from "../../../../utilites/confirmationDialog.tsx";
import {relativeDate} from "../../../../utilites/dates.ts";
import {showError, showSuccess} from "../../../../utilites/notifications.tsx";
import {CopyButton} from "../../../../components/common/CopyButton";
import classes from "./SingleSignOn.module.scss";

const DomainStatus = ({domain}: { domain: AccountSsoDomain }) => {
    switch (domain.status) {
        case 'VERIFIED':
            return <Badge size="sm" variant="light" color="green">{t`Verified`}</Badge>;
        case 'FAILING':
            return <Badge size="sm" variant="light" color="orange">{t`Failing since ${relativeDate(domain.failing_since as string)}`}</Badge>;
        case 'SUSPENDED':
            return <Badge size="sm" variant="light" color="red">{t`Suspended`}</Badge>;
        default:
            return <Badge size="sm" variant="light" color="gray">{t`Pending`}</Badge>;
    }
};

const RecordField = ({label, copyLabel, value}: { label: string, copyLabel: string, value: string }) => (
    <div className={classes.recordField}>
        <div className={classes.recordLabel}>{label}</div>
        <div className={classes.recordValue}>
            <code>{value}</code>
            <CopyButton value={value} label={copyLabel}/>
        </div>
    </div>
);

interface DomainListProps {
    accountId: IdParam;
    domains: AccountSsoDomain[];
    enforcing: boolean;
    setupLocked: boolean;
}

export const DomainList = ({accountId, domains, enforcing, setupLocked}: DomainListProps) => {
    const addMutation = useAddSsoDomain();
    const verifyMutation = useVerifySsoDomain();
    const deleteMutation = useDeleteSsoDomain();
    const formErrorHandler = useFormErrorResponseHandler();
    const form = useForm({initialValues: {domain: ''}});

    const add = (values: { domain: string }) => {
        addMutation.mutate({accountId, domain: values.domain}, {
            onSuccess: () => form.reset(),
            onError: (error) => formErrorHandler(form, error),
        });
    };

    const verify = (domain: AccountSsoDomain) => {
        verifyMutation.mutate({accountId, domainId: domain.id}, {
            onSuccess: () => showSuccess(t`${domain.domain} is verified`),
            onError: (error: any) => showError(error?.response?.data?.errors?.domain?.[0] ?? error?.response?.data?.message ?? t`Something went wrong. Please try again.`),
        });
    };

    const isLocked = (domain: AccountSsoDomain) => enforcing && domain.verified_at !== null;

    const remove = (domain: AccountSsoDomain) => {
        confirmationDialog(
            t`Remove ${domain.domain}? People with this email domain will no longer be sent to your identity provider or required to use it.`,
            () => deleteMutation.mutate({accountId, domainId: domain.id}, {
                onSuccess: () => showSuccess(t`${domain.domain} removed`),
                onError: (error: any) => showError(error?.response?.data?.message ?? t`Something went wrong. Please try again.`),
            }),
            {confirm: t`Remove domain`, destructive: true},
        );
    };

    return (
        <div className={classes.domains}>
            {domains.map(domain => (
                <div key={domain.id} className={classes.domainRow} data-testid="sso-domain-row">
                    <div className={classes.domainHeader}>
                        <span className={classes.domainName}>{domain.domain}</span>
                        <DomainStatus domain={domain}/>
                        <Tooltip
                            label={t`Stop requiring single sign-on before removing a verified domain.`}
                            disabled={!isLocked(domain)}
                            withArrow
                            multiline
                            w={240}
                        >
                            <ActionIcon
                                variant="subtle"
                                color="gray"
                                className={classes.domainRemove}
                                onClick={() => !isLocked(domain) && remove(domain)}
                                data-disabled={isLocked(domain) || undefined}
                                aria-disabled={isLocked(domain)}
                                aria-label={t`Remove ${domain.domain}`}
                            >
                                <IconTrash size={16}/>
                            </ActionIcon>
                        </Tooltip>
                    </div>
                    {domain.status !== 'VERIFIED' && (
                        <>
                            <p className={classes.muted}>
                                {domain.status === 'PENDING'
                                    ? t`Add this TXT record at your DNS provider, then verify. Subdomains need their own record.`
                                    : t`We couldn't find this TXT record in our daily check. Restore it to keep single sign-on working for this domain.`}
                            </p>
                            <RecordField label={t`Name`} copyLabel={t`Copy TXT record name`} value={domain.txt_record_name}/>
                            <RecordField label={t`Value`} copyLabel={t`Copy TXT record value`} value={domain.txt_record_value}/>
                            {!setupLocked && <div>
                                <Button
                                    size="xs"
                                    variant="light"
                                    onClick={() => verify(domain)}
                                    loading={verifyMutation.isPending && verifyMutation.variables?.domainId === domain.id}
                                >
                                    {t`Verify`}
                                </Button>
                            </div>}
                        </>
                    )}
                </div>
            ))}

            {!setupLocked && <form onSubmit={form.onSubmit(add)} className={classes.addDomain}>
                <TextInput
                    {...form.getInputProps('domain')}
                    aria-label={t`Email domain`}
                    placeholder="example.com"
                    autoComplete="off"
                    inputMode="url"
                />
                <Button type="submit" variant="default" loading={addMutation.isPending}>
                    {t`Add domain`}
                </Button>
            </form>}
        </div>
    );
};
