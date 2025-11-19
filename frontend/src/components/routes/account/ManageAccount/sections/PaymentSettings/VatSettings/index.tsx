import {useEffect, useState} from 'react';
import {t} from '@lingui/macro';
import {Alert, Button, Group, Radio, Stack, Text, TextInput, Title} from '@mantine/core';
import {IconAlertCircle, IconCheck, IconInfoCircle} from '@tabler/icons-react';
import {Card} from '../../../../../../common/Card';
import {useGetAccountVatSetting} from '../../../../../../../queries/useGetAccountVatSetting.ts';
import {useUpsertAccountVatSetting} from '../../../../../../../mutations/useUpsertAccountVatSetting.ts';
import {showError, showSuccess} from '../../../../../../../utilites/notifications.tsx';
import {Account} from '../../../../../../../types.ts';
import {getVatInfo} from '../VatNotice';
import classes from './VatSettings.module.scss';

interface VatSettingsProps {
    account: Account;
    stripeCountry?: string;
}

export const VatSettings = ({account, stripeCountry}: VatSettingsProps) => {
    const [vatRegistered, setVatRegistered] = useState<string>('');
    const [vatNumber, setVatNumber] = useState('');

    const vatSettingQuery = useGetAccountVatSetting(account.id);
    const upsertMutation = useUpsertAccountVatSetting(account.id);

    const vatInfo = getVatInfo(stripeCountry);

    if (!vatInfo.isEU) {
        return null;
    }

    const existingSettings = vatSettingQuery.data;

    useEffect(() => {
        if (existingSettings && !vatRegistered) {
            setVatRegistered(existingSettings.vat_registered ? 'yes' : 'no');
            setVatNumber(existingSettings.vat_number || '');
        }
    }, [existingSettings, vatRegistered]);

    const handleSave = async () => {
        if (vatRegistered === 'yes' && !vatNumber) {
            showError(t`Please enter your VAT number`);
            return;
        }

        if (vatRegistered === 'yes' && vatNumber.length < 10) {
            showError(t`VAT number must be at least 10 characters`);
            return;
        }

        try {
            const result = await upsertMutation.mutateAsync({
                vat_registered: vatRegistered === 'yes',
                vat_number: vatRegistered === 'yes' ? vatNumber.toUpperCase() : null,
            });

            if (result.data.vat_registered && result.data.vat_validated) {
                showSuccess(t`VAT settings saved and validated successfully`);
            } else if (result.data.vat_registered && !result.data.vat_validated) {
                showError(t`VAT settings saved but validation failed. Please check your VAT number.`);
            } else {
                showSuccess(t`VAT settings saved successfully`);
            }
        } catch (error) {
            showError(t`Failed to save VAT settings. Please try again.`);
        }
    };

    const canSave = vatRegistered && (
        vatRegistered === 'no' ||
        (vatRegistered === 'yes' && vatNumber.trim().length >= 10)
    );

    const needsVatInfo = !existingSettings || existingSettings.vat_registered === null || existingSettings.vat_registered === undefined;

    // Irish customers: Show informational message only, no form
    if (vatInfo.isIreland) {
        return (
            <div className={classes.vatSettings}>
                <Title mb={10} order={3}>{t`VAT Information`}</Title>
                <Alert color="blue" icon={<IconInfoCircle />} mb="lg">
                    <Text size="sm" fw={500} mb="xs">{t`VAT Treatment for Platform Fees`}</Text>
                    <Text size="sm" lh={1.6}>
                        {t`As your business is based in Ireland, Irish VAT at 23% applies automatically to all platform fees.`}
                    </Text>
                </Alert>
            </div>
        );
    }

    // Other EU customers: Show warning banner and form
    return (
        <div className={classes.vatSettings}>
            <Title mb={10} order={3}>{t`VAT Registration Information`}</Title>

            {needsVatInfo && (
                <Alert
                    color="orange"
                    icon={<IconAlertCircle />}
                    mb="lg"
                    styles={{
                        root: {
                            borderLeft: '4px solid var(--mantine-color-orange-6)',
                        }
                    }}
                >
                    <Text size="sm" fw={500} mb="sm">{t`Action Required: VAT Information Needed`}</Text>
                    <Text size="sm" mb="sm" lh={1.6}>
                        {t`As your business is based in the EU, we need to determine the correct VAT treatment for our platform fees:`}
                    </Text>
                    <div style={{
                        background: 'var(--mantine-color-gray-0)',
                        padding: '12px',
                        borderRadius: '6px',
                        marginBottom: '12px',
                        border: '1px solid var(--mantine-color-orange-2)'
                    }}>
                        <Text size="xs" mb="xs" c="dark.6">• {t`EU VAT-registered businesses: Reverse charge mechanism applies (0% - Article 196 of VAT Directive 2006/112/EC)`}</Text>
                        <Text size="xs" c="dark.6">• {t`Non-VAT registered businesses or individuals: Irish VAT at 23% applies`}</Text>
                    </div>
                    <div style={{
                        background: 'var(--mantine-color-white)',
                        padding: '12px',
                        borderRadius: '6px',
                        border: '1px solid var(--mantine-color-orange-2)'
                    }}>
                        <Text size="sm" fw={500} mb="xs" c="dark.7">{t`What you need to do:`}</Text>
                        <Text size="xs" mb="xs" c="dark.6">• {t`Indicate whether you're VAT-registered in the EU`}</Text>
                        <Text size="xs" c="dark.6">• {t`If registered, provide your VAT number for validation`}</Text>
                    </div>
                </Alert>
            )}

            {!needsVatInfo && (
                <Text size="sm" c="dimmed" mb="lg" lh={1.6}>
                    {t`VAT treatment for platform fees: EU VAT-registered businesses can use the reverse charge mechanism (0% - Article 196 of VAT Directive 2006/112/EC). Non-VAT registered businesses are charged Irish VAT at 23%.`}
                </Text>
            )}

            <Card variant="lightGray">
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
                                description={t`Enter your VAT number including the country code (e.g., IE1234567A for Ireland, DE123456789 for Germany)`}
                                placeholder="IE1234567A"
                                value={vatNumber}
                                onChange={(e) => setVatNumber(e.target.value)}
                                maxLength={17}
                                required
                            />

                            {existingSettings?.vat_validated && (
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

                            {existingSettings?.vat_number && !existingSettings.vat_validated && (
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
            </Card>
        </div>
    );
};
