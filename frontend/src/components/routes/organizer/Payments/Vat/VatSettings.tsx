import {t} from '@lingui/macro';
import {Text} from '@mantine/core';
import {IdParam} from '../../../../../types';
import {getVatInfo} from './VatNotice';
import {VatSettingsForm} from './VatSettingsForm';
import {useGetOrganizerVatSetting} from '../../../../../queries/useGetOrganizerVatSetting';
import {Callout} from '../../../../common/Callout';

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
            <Callout variant="info" title={t`VAT Treatment for Platform Fees`}>
                {t`As your business is based in Ireland, Irish VAT at 23% applies automatically to all platform fees.`}
            </Callout>
        );
    }

    return (
        <>
            {needsVatInfo && (
                <Callout variant="tip" title={t`Action Required: VAT Information Needed`} style={{marginBottom: 24}}>
                    {t`Tell us your VAT registration status so we apply the correct VAT treatment to platform fees.`}
                </Callout>
            )}

            {!needsVatInfo && (
                <Text size="sm" c="dimmed" mb="lg" lh={1.6}>
                    {t`VAT treatment for platform fees: EU VAT-registered businesses can use the reverse charge mechanism (0% - Article 196 of VAT Directive 2006/112/EC). Non-VAT registered businesses are charged Irish VAT at 23%.`}
                </Text>
            )}

            <VatSettingsForm organizerId={organizerId}/>
        </>
    );
};
