import {t} from '@lingui/macro';
import {Alert, Text, Title} from '@mantine/core';
import {IconAlertCircle, IconInfoCircle} from '@tabler/icons-react';
import {IdParam} from '../../../../../types';
import {getVatInfo} from './VatNotice';
import {VatSettingsForm} from './VatSettingsForm';
import {useGetOrganizerVatSetting} from '../../../../../queries/useGetOrganizerVatSetting';
import {Card} from '../../../../common/Card';

interface VatSettingsProps {
    organizerId: IdParam;
    stripeCountry?: string | null;
}

export const VatSettings = ({organizerId, stripeCountry}: VatSettingsProps) => {
    const vatSettingQuery = useGetOrganizerVatSetting(organizerId);
    const vatInfo = getVatInfo(stripeCountry);

    if (!vatInfo.isEU) {
        return null;
    }

    const existingSettings = vatSettingQuery.data;
    const needsVatInfo = existingSettings?.vat_registered == null;

    if (vatInfo.isIreland) {
        return (
            <Card>
                <Title mb={10} order={4}>{t`VAT Information`}</Title>
                <Alert color="blue" icon={<IconInfoCircle />}>
                    <Text size="sm" fw={500} mb="xs">{t`VAT Treatment for Platform Fees`}</Text>
                    <Text size="sm" lh={1.6}>
                        {t`As your business is based in Ireland, Irish VAT at 23% applies automatically to all platform fees.`}
                    </Text>
                </Alert>
            </Card>
        );
    }

    return (
        <Card>
            <Title mb={10} order={4}>{t`VAT Registration Information`}</Title>

            {needsVatInfo && (
                <Alert
                    color="orange"
                    icon={<IconAlertCircle />}
                    mb="lg"
                >
                    <Text size="sm" fw={500} mb="sm">{t`Action Required: VAT Information Needed`}</Text>
                    <Text size="sm" lh={1.6}>
                        {t`Tell us your VAT registration status so we apply the correct VAT treatment to platform fees.`}
                    </Text>
                </Alert>
            )}

            {!needsVatInfo && (
                <Text size="sm" c="dimmed" mb="lg" lh={1.6}>
                    {t`VAT treatment for platform fees: EU VAT-registered businesses can use the reverse charge mechanism (0% - Article 196 of VAT Directive 2006/112/EC). Non-VAT registered businesses are charged Irish VAT at 23%.`}
                </Text>
            )}

            <VatSettingsForm organizerId={organizerId} />
        </Card>
    );
};
