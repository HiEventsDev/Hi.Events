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
import {useEffect, useState} from "react";

const ConnectStatus = (props: { stripeDetails: StripeConnectDetails }) => {
    const [isReturningFromStripe, setIsReturningFromStripe] = useState(false);

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }
        setIsReturningFromStripe(
            window.location.search.includes('is_return') || window.location.search.includes('is_refresh')
        );
    }, []);

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
                                        if (typeof window !== 'undefined')
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

const PaymentSettings = () => {
    const accountQuery = useGetAccount();
    const stripeDetailsQuery = useCreateOrGetStripeConnectDetails(accountQuery.data?.id);
    const stripeDetails = stripeDetailsQuery.data;
    const error = stripeDetailsQuery.error as any;

    if (error?.response?.status === 403) {
        return (
            <>
                <Card className={classes.tabContent}>
                    <div className={paymentClasses.stripeInfo}>
                        <h2>{t`You do not have permission to access this page`}</h2>
                        <p>
                            {error?.response?.data?.message}
                        </p>
                    </div>
                </Card>
            </>
        );
    }

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

export default PaymentSettings;
