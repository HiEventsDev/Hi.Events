import {useEffect, useState} from 'react';
import {t} from '@lingui/macro';
import {Alert, Button, Group, Loader, Radio, Stack, Text, TextInput} from '@mantine/core';
import {IconAlertCircle, IconCheck, IconClock, IconRefresh} from '@tabler/icons-react';
import {Card} from '../../../../../../common/Card';
import {useGetAccountVatSetting} from '../../../../../../../queries/useGetAccountVatSetting.ts';
import {useUpsertAccountVatSetting} from '../../../../../../../mutations/useUpsertAccountVatSetting.ts';
import {showError, showSuccess} from '../../../../../../../utilites/notifications.tsx';
import {Account} from '../../../../../../../types.ts';
import {VatValidationStatus} from '../../../../../../../api/vat.client.ts';

interface VatSettingsFormProps {
    account: Account;
    onSuccess?: () => void;
    showCard?: boolean;
}

const EU_VAT_REGEX = /^[A-Z]{2}[0-9A-Z]{8,15}$/;

const validateVatNumber = (vatNumber: string): {valid: boolean; error?: string} => {
    const trimmed = vatNumber.trim();

    if (trimmed.includes(' ')) {
        return {valid: false, error: t`VAT number must not contain spaces`};
    }

    const upperCase = trimmed.toUpperCase();

    if (!EU_VAT_REGEX.test(upperCase)) {
        return {
            valid: false,
            error: t`VAT number must start with a 2-letter country code followed by 8-15 alphanumeric characters (e.g., DE123456789)`
        };
    }

    return {valid: true};
};

const ValidationStatusAlert = ({
    status,
    error,
    businessName,
    attempts,
}: {
    status: VatValidationStatus;
    error: string | null;
    businessName: string | null;
    attempts: number;
}) => {
    switch (status) {
        case 'VALID':
            return (
                <Alert color="green" icon={<IconCheck />}>
                    <Text size="sm" fw={500}>
                        {t`VAT number validated successfully`}
                    </Text>
                    {businessName && (
                        <Text size="xs" c="dimmed">
                            {businessName}
                        </Text>
                    )}
                </Alert>
            );

        case 'PENDING':
        case 'VALIDATING':
            return (
                <Alert color="blue" icon={status === 'VALIDATING' ? <Loader size="xs" /> : <IconClock />}>
                    <Text size="sm" fw={500}>
                        {status === 'VALIDATING'
                            ? t`Validating your VAT number...`
                            : t`Your VAT number is queued for validation`}
                    </Text>
                    <Text size="xs" c="dimmed">
                        {t`We'll validate your VAT number in the background. If there are any issues, we'll let you know.`}
                    </Text>
                </Alert>
            );

        case 'INVALID':
            return (
                <Alert color="red" icon={<IconAlertCircle />}>
                    <Text size="sm" fw={500}>
                        {t`VAT number validation failed`}
                    </Text>
                    <Text size="xs" c="dimmed">
                        {error || t`The VAT number could not be validated. Please check the number and try again.`}
                    </Text>
                </Alert>
            );

        case 'FAILED':
            return (
                <Alert color="orange" icon={<IconRefresh />}>
                    <Text size="sm" fw={500}>
                        {t`VAT validation service temporarily unavailable`}
                    </Text>
                    <Text size="xs" c="dimmed">
                        {t`We were unable to validate your VAT number after multiple attempts. We'll continue trying in the background. Please check back later.`}
                        {attempts > 0 && ` (${t`Attempts`}: ${attempts})`}
                    </Text>
                </Alert>
            );

        default:
            return null;
    }
};

export const VatSettingsForm = ({account, onSuccess, showCard = true}: VatSettingsFormProps) => {
    const [vatRegistered, setVatRegistered] = useState<string>('');
    const [vatNumber, setVatNumber] = useState('');
    const [vatError, setVatError] = useState<string | undefined>();

    const shouldPoll = vatNumber.trim().length > 0;

    const vatSettingQuery = useGetAccountVatSetting(account.id, {
        refetchInterval: (query) => {
            if (!shouldPoll) {
                return false;
            }
            const data = query.state.data;
            if (data?.vat_validation_status === 'PENDING' || data?.vat_validation_status === 'VALIDATING') {
                return 5000;
            }
            return false;
        },
    });
    const upsertMutation = useUpsertAccountVatSetting(account.id);

    const existingSettings = vatSettingQuery.data;

    useEffect(() => {
        if (existingSettings && !vatRegistered) {
            setVatRegistered(existingSettings.vat_registered ? 'yes' : 'no');
            setVatNumber(existingSettings.vat_number || '');
        }
    }, [existingSettings, vatRegistered]);

    const handleVatNumberChange = (value: string) => {
        setVatNumber(value);
        if (vatError) {
            setVatError(undefined);
        }
    };

    const handleSave = async () => {
        if (vatRegistered === 'yes' && !vatNumber) {
            showError(t`Please enter your VAT number`);
            return;
        }

        if (vatRegistered === 'yes') {
            const validation = validateVatNumber(vatNumber);
            if (!validation.valid) {
                setVatError(validation.error);
                showError(validation.error || t`Invalid VAT number format`);
                return;
            }
        }

        try {
            const result = await upsertMutation.mutateAsync({
                vat_registered: vatRegistered === 'yes',
                vat_number: vatRegistered === 'yes' ? vatNumber.toUpperCase().trim() : null,
            });

            if (!result.data.vat_registered) {
                showSuccess(t`VAT settings saved successfully`);
            } else if (result.data.vat_validation_status === 'VALID') {
                showSuccess(t`VAT number validated successfully`);
            } else if (result.data.vat_validation_status === 'INVALID') {
                showError(t`VAT number validation failed. Please check your VAT number.`);
            } else if (result.data.vat_validation_status === 'PENDING') {
                showSuccess(t`VAT settings saved. We're validating your VAT number in the background.`);
            } else {
                showSuccess(t`VAT settings saved successfully`);
            }

            onSuccess?.();
        } catch {
            showError(t`Failed to save VAT settings. Please try again.`);
        }
    };

    const canSave = vatRegistered && (
        vatRegistered === 'no' ||
        (vatRegistered === 'yes' && vatNumber.trim().length >= 10)
    );

    const isCurrentVatNumber = existingSettings?.vat_number?.toUpperCase() === vatNumber.toUpperCase().trim();

    const formContent = (
        <Stack gap="lg">
            <Radio.Group
                value={vatRegistered}
                onChange={setVatRegistered}
                label={t`Are you VAT registered in the EU?`}
                required
            >
                <Group mt="xs">
                    <Radio
                        value="no"
                        label={t`No - I'm an individual or non-VAT registered business`}
                    />
                    <Radio
                        value="yes"
                        label={t`Yes - I have a valid EU VAT registration number`}
                    />
                </Group>
            </Radio.Group>

            {vatRegistered === 'yes' && (
                <Stack gap="md">
                    <TextInput
                        label={t`VAT Number`}
                        description={t`Enter your VAT number including the country code, without spaces (e.g., IE1234567A, DE123456789)`}
                        placeholder="IE1234567A"
                        value={vatNumber}
                        onChange={(e) => handleVatNumberChange(e.target.value)}
                        maxLength={17}
                        required
                        error={vatError}
                    />

                    {existingSettings?.vat_number && !vatError && isCurrentVatNumber && (
                        <ValidationStatusAlert
                            status={existingSettings.vat_validation_status}
                            error={existingSettings.vat_validation_error}
                            businessName={existingSettings.business_name}
                            attempts={existingSettings.vat_validation_attempts}
                        />
                    )}
                </Stack>
            )}

            <Group>
                <Button
                    onClick={handleSave}
                    loading={upsertMutation.isPending}
                    disabled={!canSave}
                >
                    {t`Save VAT Settings`}
                </Button>
                {vatRegistered === 'yes' && !isCurrentVatNumber && (
                    <Text size="xs" c="dimmed">
                        {t`Your VAT number will be validated when you save`}
                    </Text>
                )}
            </Group>
        </Stack>
    );

    if (showCard) {
        return <Card variant="lightGray">{formContent}</Card>;
    }

    return formContent;
};
