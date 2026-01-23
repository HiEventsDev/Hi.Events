import {t} from '@lingui/macro';
import {Alert, Text, Title} from '@mantine/core';
import {IconAlertCircle, IconInfoCircle} from '@tabler/icons-react';
import {Account} from '../../../../../../../types.ts';
import {getVatInfo} from '../VatNotice';
import {VatSettingsForm} from './VatSettingsForm.tsx';
import {useGetAccountVatSetting} from '../../../../../../../queries/useGetAccountVatSetting.ts';
import classes from './VatSettings.module.scss';

interface VatSettingsProps {
    account: Account;
    stripeCountry?: string;
}

export const VatSettings = ({account, stripeCountry}: VatSettingsProps) => {
    const vatSettingQuery = useGetAccountVatSetting(account.id);
    const vatInfo = getVatInfo(stripeCountry);

    if (!vatInfo.isEU) {
        return null;
    }

    const existingSettings = vatSettingQuery.data;
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

            <VatSettingsForm account={account} />
        </div>
    );
};
