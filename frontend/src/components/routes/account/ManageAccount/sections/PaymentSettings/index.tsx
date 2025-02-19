import {t} from "@lingui/macro";
import {Card} from "../../../../../common/Card";
import {HeadingCard} from "../../../../../common/HeadingCard";
import {useCreateOrGetStripeConnectDetails} from "../../../../../../queries/useCreateOrGetStripeConnectDetails.ts";
import {useGetAccount} from "../../../../../../queries/useGetAccount.ts";
import {LoadingMask} from "../../../../../common/LoadingMask";
import {Anchor, Button, Grid, Group, Text, ThemeIcon, Title} from "@mantine/core";
import {StripeConnectDetails} from "../../../../../../types.ts";
import paymentClasses from "./PaymentSettings.module.scss";
import classes from "../../ManageAccount.module.scss";
import {useEffect, useState} from "react";
import {IconAlertCircle, IconBrandStripe, IconCheck, IconExternalLink} from '@tabler/icons-react';
import {formatCurrency} from "../../../../../../utilites/currency.ts";

interface FeePlanDisplayProps {
    configuration?: {
        name: string;
        application_fees: {
            percentage: number;
            fixed: number;
        };
        is_system_default: boolean;
    };
}

const formatPercentage = (value: number) => {
    return new Intl.NumberFormat('en-US', {
        style: 'percent',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(value / 100);
};

const FeePlanDisplay = ({configuration}: FeePlanDisplayProps) => {
    if (!configuration) return null;

    return (
        <div className={paymentClasses.stripeInfo}>
            <Title mb={10} order={3}>{t`Platform Fees`}</Title>

            <Text size="sm" c="dimmed" mb="lg">
                {t`Hi.Events charges platform fees to maintain and improve our services. These fees are automatically deducted from each transaction.`}
            </Text>

            <Card variant={'lightGray'}>
                <Title order={4}>{configuration.name}</Title>
                <Grid>
                    <Grid.Col span={{base: 12, sm: 6}}>
                        <Group gap="xs" wrap={'nowrap'}>
                            <Text size="sm">
                                {t`Transaction Fee:`}{' '}
                                <Text span fw={600}>
                                    {formatPercentage(configuration.application_fees.percentage)}
                                </Text>
                            </Text>
                        </Group>
                    </Grid.Col>
                    <Grid.Col span={{base: 12, sm: 6}}>
                        <Group gap="xs" wrap={'nowrap'}>
                            <Text size="sm">
                                {t`Fixed Fee:`}{' '}
                                <Text span fw={600}>
                                    {formatCurrency(configuration.application_fees.fixed)}
                                </Text>
                            </Text>
                        </Group>
                    </Grid.Col>
                </Grid>
            </Card>

            <Text size="xs" c="dimmed" mt="md">
                <Group gap="xs" align="center" wrap={'nowrap'}>
                    <IconAlertCircle size={14}/>
                    <Text
                        span>{t`Fees are subject to change. You will be notified of any changes to your fee structure.`}</Text>
                </Group>
            </Text>
        </div>
    );
};

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
            <Title mb={10} order={3}>{t`Payment Processing`}</Title>

            {props.stripeDetails?.is_connect_setup_complete ? (
                <>
                    <Group gap="xs" mb="md">
                        <ThemeIcon size="sm" variant="light" radius="xl" color="green">
                            <IconCheck size={16}/>
                        </ThemeIcon>
                        <Text size="sm" fw={500}>
                            <b>
                                {t`Connected to Stripe`}
                            </b>
                        </Text>
                    </Group>
                    <Text size="sm" c="dimmed" mb="lg">
                        {t`Your Stripe account is connected and ready to process payments.`}
                    </Text>
                    <Group gap="xs">
                        <Anchor
                            href="https://dashboard.stripe.com/"
                            target="_blank"
                            size="sm"
                        >
                            <Group gap="xs" wrap={'nowrap'}>
                                <Text span>{t`Go to Stripe Dashboard`}</Text>
                                <IconExternalLink size={14}/>
                            </Group>
                        </Anchor>
                        <Text span c="dimmed">•</Text>
                        <Anchor
                            href="https://stripe.com/docs/connect"
                            target="_blank"
                            size="sm"
                        >
                            <Group gap="xs">
                                <Text span>{t`Connect Documentation`}</Text>
                                <IconExternalLink size={14}/>
                            </Group>
                        </Anchor>
                    </Group>
                </>
            ) : (
                <>
                    <Text size="sm" c="dimmed" mb="lg">
                        {t`To receive credit card payments, you need to connect your Stripe account. Stripe is our payment processing partner that ensures secure transactions and timely payouts.`}
                    </Text>
                    <Group gap="md">
                        <Button
                            variant="light"
                            size="sm"
                            leftSection={<IconBrandStripe size={20}/>}
                            onClick={() => {
                                if (typeof window !== 'undefined')
                                    window.location.href = String(props.stripeDetails?.connect_url);
                            }}
                        >
                            {(!isReturningFromStripe) && t`Connect with Stripe`}
                            {(isReturningFromStripe) && t`Complete Stripe Setup`}
                        </Button>
                        <Group gap="xs">
                            <Anchor
                                href="https://stripe.com/connect"
                                target="_blank"
                                size="sm"
                            >
                                <Group gap="xs">
                                    <Text span>{t`About Stripe Connect`}</Text>
                                    <IconExternalLink size={14}/>
                                </Group>
                            </Anchor>
                            <Text span c="dimmed">•</Text>
                            <Anchor
                                href="https://stripe.com/docs/connect"
                                target="_blank"
                                size="sm"
                            >
                                <Group gap="xs">
                                    <Text span>{t`Documentation`}</Text>
                                    <IconExternalLink size={14}/>
                                </Group>
                            </Anchor>
                        </Group>
                    </Group>
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
                        <Group gap="xs" mb="md">
                            <ThemeIcon size="lg" radius="md" variant="light">
                                <IconAlertCircle size={20}/>
                            </ThemeIcon>
                            <Title order={2}>{t`Access Denied`}</Title>
                        </Group>
                        <Text size="md">
                            {error?.response?.data?.message}
                        </Text>
                    </div>
                </Card>
            </>
        );
    }

    return (
        <>
            <HeadingCard
                heading={t`Payment Settings`}
                subHeading={t`Manage your payment processing and view platform fees`}
            />
            <Card className={classes.tabContent}>
                <LoadingMask/>
                {(accountQuery.data?.configuration || stripeDetails) && (
                    <Grid gutter="xl">
                        <Grid.Col span={{base: 12, md: 6}}>
                            {stripeDetails && <ConnectStatus stripeDetails={stripeDetails}/>}
                        </Grid.Col>
                        <Grid.Col span={{base: 12, md: 6}}>
                            {accountQuery.data?.configuration && (
                                <FeePlanDisplay configuration={accountQuery.data.configuration}/>
                            )}
                        </Grid.Col>
                    </Grid>
                )}
            </Card>
        </>
    );
};

export default PaymentSettings;
