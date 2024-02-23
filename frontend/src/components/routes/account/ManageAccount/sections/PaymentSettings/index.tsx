import {t} from "@lingui/macro";
import {Card} from "../../../../../common/Card";
import {HeadingCard} from "../../../../../common/HeadingCard";
import {useCreateOrGetStripeConnectDetails} from "../../../../../../queries/useCreateOrGetStripeConnectDetails.ts";
import {useGetAccount} from "../../../../../../queries/useGetAccount.ts";
import {LoadingMask} from "../../../../../common/LoadingMask";
import {Anchor, Button, Group} from "@mantine/core";
import {StripeConnectDetails} from "../../../../../../types.ts";
import paymentClasses from "./PaymentSettings.module.scss"
import classes from "../../ManageAccount.module.scss"

const ConnectStatus = (props: { stripeDetails: StripeConnectDetails }) => {
    const isReturn = window.location.search.includes('is_return');
    const isRefresh = window.location.search.includes('is_refresh');
    const isReturningFromStripe = isReturn || isRefresh;

    return (
        <div className={paymentClasses.stripeInfo}>
            {props.stripeDetails?.is_connect_setup_complete && (
                <>
                    <h2>{t`You have connected your Stripe account`}</h2>
                    <p>
                        {t`You can now start receiving payments through Stripe.`}
                    </p>
                </>
            )}
            {!props.stripeDetails?.is_connect_setup_complete && (
                <>
                    <h2>
                        {!isReturningFromStripe && t`You have not connected your Stripe account`}
                        {isReturningFromStripe && t`You have not completed your Stripe Connect setup`}
                    </h2>
                    <p>
                        {t`We use Stripe to process payments. Connect your Stripe account to start receiving payments.`}
                    </p>
                    <p>
                        <Group gap={20}>
                            <Button variant={'light'}
                                    onClick={() => {
                                        window.location.href = String(props.stripeDetails?.connect_url);
                                    }}
                            >
                                {(!isReturningFromStripe) && t`Connect Stripe`}
                                {(isReturningFromStripe) && t`Continue Stripe Connect Setup`}
                            </Button>
                            <Anchor target={'_blank'} href={'https://stripe.com/'}>
                                {t`Learn more about Stripe`}
                            </Anchor>
                        </Group>
                    </p>
                </>
            )}
        </div>
    );
};

export const PaymentSettings = () => {
    const accountQuery = useGetAccount();
    const stripeDetailsQuery = useCreateOrGetStripeConnectDetails(accountQuery.data?.id);
    const stripeDetails = stripeDetailsQuery.data;

    return (
        <>
            <HeadingCard
                heading={t`Payment`}
                subHeading={t`Manage your Stripe payment details`}
            />
            <Card className={classes.tabContent}>
                <LoadingMask/>
                {stripeDetails && <ConnectStatus stripeDetails={stripeDetails}/>}
            </Card>
        </>
    );
};
