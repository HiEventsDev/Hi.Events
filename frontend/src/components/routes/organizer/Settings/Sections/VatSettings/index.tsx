import {t} from "@lingui/macro";
import {useParams} from "react-router";
import {Card} from "../../../../../common/Card";
import {HeadingWithDescription} from "../../../../../common/Card/CardHeading";
import {useGetOrganizerStripeConnect} from "../../../../../../queries/useGetOrganizerStripeConnect";
import {VatSettings as VatSettingsBody} from "../../../Payments/Vat/VatSettings";

export const VatSettings = () => {
    const {organizerId} = useParams();
    const stripeQuery = useGetOrganizerStripeConnect(organizerId, {enabled: !!organizerId});

    const data = stripeQuery.data;
    const primaryAccount = (data?.primary_stripe_account_id
        ? data?.stripe_connect_accounts?.find(a => a.stripe_account_id === data.primary_stripe_account_id)
        : undefined)
        ?? data?.stripe_connect_accounts?.[0];

    return (
        <Card>
            <HeadingWithDescription
                heading={t`VAT`}
                description={t`How VAT is applied to the platform fees we charge you. Determined by the country of your connected Stripe account.`}
            />
            {organizerId && (
                <VatSettingsBody organizerId={organizerId} stripeCountry={primaryAccount?.country}/>
            )}
        </Card>
    );
};
