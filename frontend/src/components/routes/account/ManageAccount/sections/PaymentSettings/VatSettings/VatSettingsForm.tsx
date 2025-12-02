import {useEffect, useState} from 'react';
import {t} from '@lingui/macro';
import {Alert, Button, Group, Radio, Stack, Text, TextInput} from '@mantine/core';
import {IconAlertCircle, IconCheck} from '@tabler/icons-react';
import {Card} from '../../../../../../common/Card';
import {useGetAccountVatSetting} from '../../../../../../../queries/useGetAccountVatSetting.ts';
import {useUpsertAccountVatSetting} from '../../../../../../../mutations/useUpsertAccountVatSetting.ts';
import {showError, showSuccess} from '../../../../../../../utilites/notifications.tsx';
import {Account} from '../../../../../../../types.ts';

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

export const VatSettingsForm = ({account, onSuccess, showCard = true}: VatSettingsFormProps) => {
    const [vatRegistered, setVatRegistered] = useState<string>('');
    const [vatNumber, setVatNumber] = useState('');
    const [vatError, setVatError] = useState<string | undefined>();

    const vatSettingQuery = useGetAccountVatSetting(account.id);
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

            if (result.data.vat_registered && result.data.vat_validated) {
                showSuccess(t`VAT settings saved and validated successfully`);
            } else if (result.data.vat_registered && !result.data.vat_validated) {
                showError(t`VAT settings saved but validation failed. Please check your VAT number.`);
            } else {
                showSuccess(t`VAT settings saved successfully`);
            }

            onSuccess?.();
        } catch (error) {
            showError(t`Failed to save VAT settings. Please try again.`);
        }
    };

    const canSave = vatRegistered && (
        vatRegistered === 'no' ||
        (vatRegistered === 'yes' && vatNumber.trim().length >= 10)
    );

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

                    {existingSettings?.vat_validated && !vatError &&
                     existingSettings.vat_number?.toUpperCase() === vatNumber.toUpperCase().trim() && (
                        <Alert color="green" icon={<IconCheck />}>
                            <Text size="sm" fw={500}>
                                {t`Valid VAT number`}
                            </Text>
                            {existingSettings.business_name && (
                                <Text size="xs" c="dimmed">
                                    {existingSettings.business_name}
                                </Text>
                            )}
                        </Alert>
                    )}

                    {existingSettings?.vat_number && !existingSettings.vat_validated && !vatError &&
                     existingSettings.vat_number?.toUpperCase() === vatNumber.toUpperCase().trim() && (
                        <Alert color="red" icon={<IconAlertCircle />}>
                            <Text size="sm">
                                {t`VAT number validation failed. Please check your number and try again.`}
                            </Text>
                        </Alert>
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
                {vatRegistered === 'yes' && (
                    <Text size="xs" c="dimmed">
                        {t`Your VAT number will be validated automatically when you save`}
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
