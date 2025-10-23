import {t} from "@lingui/macro";
import {HeadingCard} from "../../../../../common/HeadingCard";
import {useCreateOrGetStripeConnectDetails} from "../../../../../../queries/useCreateOrGetStripeConnectDetails.ts";
import {useGetAccount} from "../../../../../../queries/useGetAccount.ts";
import {useGetStripeConnectAccounts} from "../../../../../../queries/useGetStripeConnectAccounts.ts";
import {LoadingMask} from "../../../../../common/LoadingMask";
import {Anchor, Button, Grid, Group, Text, ThemeIcon, Title} from "@mantine/core";
import {Account, StripeConnectAccountsResponse} from "../../../../../../types.ts";
import paymentClasses from "./PaymentSettings.module.scss";
import classes from "../../ManageAccount.module.scss";
import {useEffect, useState} from "react";
import {IconAlertCircle, IconBrandStripe, IconCheck, IconExternalLink, IconInfoCircle} from '@tabler/icons-react';
import {Card} from "../../../../../common/Card";
import {formatCurrency} from "../../../../../../utilites/currency.ts";
import {showSuccess} from "../../../../../../utilites/notifications.tsx";
import {getConfig} from "../../../../../../utilites/config.ts";
import {isHiEvents} from "../../../../../../utilites/helpers.ts";

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

const MigrationNotice = ({stripeData}: { stripeData: StripeConnectAccountsResponse }) => {
    const caAccount = stripeData.stripe_connect_accounts.find(acc => acc.platform === 'ca');
    const ieAccount = stripeData.stripe_connect_accounts.find(acc => acc.platform === 'ie');

    // Only show if Hi.Events user has CA account but no completed IE account
    if (!isHiEvents() || !caAccount || (ieAccount && ieAccount.is_setup_complete)) {
        return null;
    }

    return (
        <Card
            variant="lightGray"
            className={paymentClasses.migrationNotice}
        >
            <Group gap="md" mb="md" align="flex-start">
                <ThemeIcon
                    size="lg"
                    variant="light"
                    radius="xl"
                    color="blue"
                    style={{marginTop: '2px'}}
                >
                    <IconInfoCircle size={20}/>
                </ThemeIcon>

                <div style={{flex: 1}}>
                    <Title order={3} mb="sm" c="blue.8">{t`Action Required: Reconnect Your Stripe Account`}</Title>

                    <Text size="sm" mb="md" lh={1.5} c="dark.6">
                        {t`We've officially moved our headquarters to Ireland 🇮🇪. As part of this transition, we're now using Stripe Ireland instead of Stripe Canada. To keep your payouts running smoothly, you'll need to reconnect your Stripe account.`}
                    </Text>

                    <div style={{
                        background: 'var(--mantine-color-gray-0)',
                        padding: '16px',
                        borderRadius: '6px',
                        marginBottom: '16px',
                        border: '1px solid var(--mantine-color-gray-2)'
                    }}>
                        <Text size="sm" fw={500} mb="sm" c="dark.7">{t`Here's what to expect:`}</Text>
                        <Text size="xs" mb="xs" c="dark.6">• {t`Takes just a few minutes`}</Text>
                        <Text size="xs" mb="xs" c="dark.6">• {t`No impact on your current or past transactions`}</Text>
                        <Text size="xs" c="dark.6">• {t`Payments will continue to flow without interruption`}</Text>
                    </div>

                    <Text size="xs" c="dimmed" fs="italic">
                        {t`Thanks for your support as we continue to grow and improve Hi.Events!`}
                    </Text>
                </div>
            </Group>
        </Card>
    );
};

const MigrationBanner = ({stripeData}: { stripeData: StripeConnectAccountsResponse }) => {
    const caAccount = stripeData.stripe_connect_accounts.find(acc => acc.platform === 'ca');
    const ieAccount = stripeData.stripe_connect_accounts.find(acc => acc.platform === 'ie');

    // Only show if user has CA account but no completed IE account
    if (!isHiEvents() || !caAccount || (ieAccount && ieAccount.is_setup_complete)) {
        return null;
    }

    return (
        <Card variant="lightGray" className={paymentClasses.migrationBanner}>
            <Group gap="sm" mb="md">
                <ThemeIcon size="lg" variant="light" radius="xl" color="blue">
                    <IconInfoCircle size={20}/>
                </ThemeIcon>
                <div>
                    <Title order={3}>{t`Complete the setup below to continue`}</Title>
                </div>
            </Group>

            <Text size="sm" c="dimmed">
                {t`Just click the button below to reconnect your Stripe account.`}
            </Text>
        </Card>
    );
};

const PlatformPanel = ({
                           platform,
                           account,
                           isActive,
                           onSetupStripe,
                           hideLabels = false,
                           isMigrationComplete = false,
                       }: {
    platform: 'ca' | 'ie';
    account: any;
    isActive: boolean;
    onSetupStripe: () => void;
    hideLabels?: boolean;
    isMigrationComplete?: boolean;
}) => {
    const platformColors = {
        ca: 'orange',
        ie: 'green'
    };

    return (
        <Card
            variant="default"
            className={`${paymentClasses.platformPanel} ${isActive ? paymentClasses.activePlatform : ''} ${paymentClasses[platform]}`}
        >
            <Group gap="sm" mb="md" justify="space-between">
                <Group gap="sm">
                    <ThemeIcon
                        size="md"
                        variant="light"
                        radius="xl"
                        color={platformColors[platform]}
                    >
                        {account?.is_setup_complete ? <IconCheck size={16}/> : <IconAlertCircle size={16}/>}
                    </ThemeIcon>
                    <div>
                        <Title order={4}>{t`Stripe Connect`}</Title>
                        {isActive && !hideLabels && !isMigrationComplete && (
                            <Text size="xs" c="dimmed">{t`Current payment processor`}</Text>
                        )}
                    </div>
                </Group>
                {!hideLabels && platform === 'ca' && isActive && (
                    <Text size="xs" c="orange" fw={500}>
                        {t`Upgrade Available`}
                    </Text>
                )}
            </Group>

            {account?.is_setup_complete ? (
                <>
                    <Text size="sm" c="dimmed" mb="md">
                        {hideLabels
                            ? t`Your Stripe account is connected and processing payments.`
                            : (platform === 'ca'
                                    ? (isActive
                                            ? t`You're all set! Your payments are being processed smoothly.`
                                            : t`Still handling refunds for your older transactions.`
                                    )
                                    : t`All done! You're now using our upgraded payment system.`
                            )
                        }
                    </Text>
                    <Group gap="xs">
                        <Anchor
                            href="https://dashboard.stripe.com/"
                            target="_blank"
                            size="sm"
                        >
                            <Group gap="xs" wrap={'nowrap'}>
                                <Text span>{t`Open Stripe Dashboard`}</Text>
                                <IconExternalLink size={14}/>
                            </Group>
                        </Anchor>
                    </Group>
                </>
            ) : account ? (
                <>
                    <Text size="sm" c="dimmed" mb="md">
                        {t`Almost there! Finish connecting your Stripe account to start accepting payments.`}
                    </Text>
                    <Button
                        variant="light"
                        size="sm"
                        leftSection={<IconBrandStripe size={16}/>}
                        onClick={onSetupStripe}
                        color={platformColors[platform]}
                    >
                        {t`Finish Setup`}
                    </Button>
                </>
            ) : (
                <>
                    <Text size="sm" c="dimmed" mb="md">
                        {hideLabels
                            ? t`Connect your Stripe account to start accepting payments.`
                            : (platform === 'ca'
                                    ? t`Connect your Stripe account to accept payments.`
                                    : t`Ready to upgrade? This takes only a few minutes.`
                            )
                        }
                    </Text>
                    <Button
                        variant="light"
                        size="sm"
                        leftSection={<IconBrandStripe size={16}/>}
                        onClick={onSetupStripe}
                        color={platformColors[platform]}
                    >
                        {platform === 'ie' && !hideLabels ? t`Connect & Upgrade` : t`Connect with Stripe`}
                    </Button>
                </>
            )}
        </Card>
    );
};

const FeePlanDisplay = ({configuration}: FeePlanDisplayProps) => {
    if (!configuration) return null;

    return (
        <div className={paymentClasses.stripeInfo}>
            <Title mb={10} order={3}>{t`Platform Fees`}</Title>

            <Text size="sm" c="dimmed" mb="lg">
                {getConfig("VITE_APP_NAME", "Hi.Events")} charges platform fees to maintain and improve our services.
                These fees are automatically deducted from each transaction.
            </Text>

            <Card variant={'lightGray'}>
                <Title order={4}>{configuration.name}</Title>
                <Grid>
                    {configuration.application_fees.percentage > 0 && (
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
                    )}
                    {configuration.application_fees.fixed > 0 && (
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
                    )}
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

// Hi.Events Cloud Multi-Platform Component
const HiEventsConnectStatus = ({account}: { account: Account }) => {
    const [fetchStripeDetails, setFetchStripeDetails] = useState(false);
    const [platformToSetup, setPlatformToSetup] = useState<string | undefined>();

    const stripeAccountsQuery = useGetStripeConnectAccounts(account.id);
    const stripeDetailsQuery = useCreateOrGetStripeConnectDetails(
        account.id,
        fetchStripeDetails,
        platformToSetup
    );

    const stripeData = stripeAccountsQuery.data;
    const stripeDetails = stripeDetailsQuery.data;
    const error = stripeDetailsQuery.error as any;

    // Check if this is a new user (no platforms set up yet)
    const isNewUser = stripeData &&
        stripeData.stripe_connect_accounts.length === 0 &&
        !stripeData.account.stripe_platform;

    const handleSetupStripe = (platform: 'ca' | 'ie') => {
        setPlatformToSetup(platform);
        if (!stripeDetails) {
            setFetchStripeDetails(true);
            return;
        } else if (stripeDetails.connect_url) {
            if (typeof window !== 'undefined') {
                showSuccess(t`Redirecting to Stripe...`);
                window.location.href = stripeDetails.connect_url;
            }
        } else {
            // Setup is already complete, refresh the accounts data
            stripeAccountsQuery.refetch();
        }
    };

    useEffect(() => {
        if (fetchStripeDetails && !stripeDetailsQuery.isLoading) {
            setFetchStripeDetails(false);
            if (stripeDetails?.connect_url) {
                showSuccess(t`Redirecting to Stripe...`);
                window.location.href = stripeDetails.connect_url;
            } else if (stripeDetails) {
                if (stripeDetails.is_connect_setup_complete) {
                    showSuccess(t`Account already connected!`);
                }
                // Refresh the stripe accounts data to get the new account
                stripeAccountsQuery.refetch();
            }
        }
    }, [fetchStripeDetails, stripeDetailsQuery.isFetched, stripeDetails, stripeAccountsQuery]);

    if (error?.response?.status === 403) {
        return (
            <Card>
                <Group gap="xs" mb="md">
                    <ThemeIcon size="lg" radius="md" variant="light">
                        <IconAlertCircle size={20}/>
                    </ThemeIcon>
                    <Title order={2}>{t`Access Denied`}</Title>
                </Group>
                <Text size="md">
                    {error?.response?.data?.message}
                </Text>
            </Card>
        );
    }

    if (!stripeData) {
        return <LoadingMask/>;
    }

    const caAccount = stripeData.stripe_connect_accounts.find(acc => acc.platform === 'ca');
    const ieAccount = stripeData.stripe_connect_accounts.find(acc => acc.platform === 'ie');
    const activePlatform = stripeData.account.stripe_platform;

    // For new Hi.Events users with no CA platform (either new or only IE)
    // Show simple setup without migration messaging
    if (isNewUser || (!caAccount && ieAccount)) {
        const hasIrelandAccount = !!ieAccount;
        const isIrelandComplete = ieAccount?.is_setup_complete === true;

        let content;

        if (isIrelandComplete) {
            // CASE 1: Ireland account exists and is fully set up
            content = (
                <>
                    <Group gap="xs" mb="md">
                        <ThemeIcon size="sm" variant="light" radius="xl" color="green">
                            <IconCheck size={16}/>
                        </ThemeIcon>
                        <Text size="sm" fw={500}>
                            <b>{t`Connected to Stripe`}</b>
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
                                <Text span>{t`Open Stripe Dashboard`}</Text>
                                <IconExternalLink size={14}/>
                            </Group>
                        </Anchor>
                    </Group>
                </>
            );
        } else if (hasIrelandAccount && !isIrelandComplete) {
            // CASE 2: Ireland account exists but setup is incomplete
            content = (
                <>
                    <Text size="sm" c="dimmed" mb="lg">
                        {t`Almost there! Finish connecting your Stripe account to start accepting payments.`}
                    </Text>
                    <Button
                        variant="light"
                        size="sm"
                        leftSection={<IconBrandStripe size={20}/>}
                        onClick={() => handleSetupStripe('ie')}
                    >
                        {t`Finish Stripe Setup`}
                    </Button>
                    <Group gap="xs" mt="md">
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
                    </Group>
                </>
            );
        } else {
            // CASE 3: No account exists yet - completely new user
            content = (
                <>
                    <Text size="sm" c="dimmed" mb="lg">
                        {t`Connect your Stripe account to start accepting payments for your events.`}
                    </Text>
                    <Button
                        variant="light"
                        size="sm"
                        leftSection={<IconBrandStripe size={20}/>}
                        onClick={() => handleSetupStripe((account?.stripe_hi_events_primary_platform || 'ie') as 'ca' | 'ie')}
                    >
                        {t`Connect with Stripe`}
                    </Button>
                    <Group gap="xs" mt="md">
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
                    </Group>
                </>
            );
        }

        return (
            <div className={paymentClasses.stripeInfo}>
                <Title mb={10} order={3}>{t`Payment Processing`}</Title>
                {content}
            </div>
        );
    }

    // Migration logic for users with CA account
    const isMigrationComplete = ieAccount?.is_setup_complete === true;
    const shouldShowCaAccount = caAccount?.is_setup_complete === true; // Only show CA if it's complete

    return (
        <div className={paymentClasses.stripeInfo}>
            <Title mb={10} order={3}>{t`Payment Processing`}</Title>

            <MigrationBanner stripeData={stripeData}/>

            {/* Show active platform first */}
            {activePlatform === 'ie' ? (
                <>
                    {/* Ireland Platform (Active) */}
                    <PlatformPanel
                        platform="ie"
                        account={ieAccount}
                        isActive={true}
                        onSetupStripe={() => handleSetupStripe('ie')}
                        hideLabels={isMigrationComplete && !shouldShowCaAccount}
                        isMigrationComplete={isMigrationComplete}
                    />

                    {/* Canada Platform (Legacy) - Only show if complete and migration not done */}
                    {shouldShowCaAccount && !isMigrationComplete && (
                        <PlatformPanel
                            platform="ca"
                            account={caAccount}
                            isActive={false}
                            onSetupStripe={() => handleSetupStripe('ca')}
                            hideLabels={false}
                        />
                    )}
                </>
            ) : (
                <>
                    {/* Canada Platform (Active) - Only show if complete */}
                    {shouldShowCaAccount && (
                        <PlatformPanel
                            platform="ca"
                            account={caAccount}
                            isActive={true}
                            onSetupStripe={() => handleSetupStripe('ca')}
                            isMigrationComplete={isMigrationComplete}
                        />
                    )}

                    {/* Ireland Platform - Always show if CA is active (for migration) */}
                    {shouldShowCaAccount && !isMigrationComplete && (
                        <PlatformPanel
                            platform="ie"
                            account={ieAccount}
                            isActive={false}
                            onSetupStripe={() => handleSetupStripe('ie')}
                        />
                    )}

                    {/* If no complete CA account, just show IE setup */}
                    {!shouldShowCaAccount && (
                        <PlatformPanel
                            platform="ie"
                            account={ieAccount}
                            isActive={false}
                            onSetupStripe={() => handleSetupStripe('ie')}
                            hideLabels={true}
                        />
                    )}
                </>
            )}

            {/* Helpful note during migration only */}
            {shouldShowCaAccount && ieAccount && !isMigrationComplete && (
                <Text size="xs" c="dimmed" mt="md">
                    {t`Once you complete the upgrade, your old account will only be used for refunds.`}
                </Text>
            )}
        </div>
    );
};

// Open-Source Simple Component (like original)
const OpenSourceConnectStatus = ({account}: { account: Account }) => {
    const [fetchStripeDetails, setFetchStripeDetails] = useState(false);
    const [isReturningFromStripe, setIsReturningFromStripe] = useState(false);
    const stripeDetailsQuery = useCreateOrGetStripeConnectDetails(
        account.id,
        !!account?.stripe_account_id || fetchStripeDetails,
        undefined // No platform for open-source
    );

    const stripeDetails = stripeDetailsQuery.data;
    const error = stripeDetailsQuery.error as any;

    const handleSetupStripe = () => {
        if (!stripeDetails) {
            setFetchStripeDetails(true);
            return;
        } else {
            if (typeof window !== 'undefined') {
                showSuccess(t`Redirecting to Stripe...`);
                window.location.href = String(stripeDetails?.connect_url);
            }
        }
    };

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }
        setIsReturningFromStripe(
            window.location.search.includes('is_return') || window.location.search.includes('is_refresh')
        );
    }, []);

    useEffect(() => {
        if (fetchStripeDetails && !stripeDetailsQuery.isLoading) {
            setFetchStripeDetails(false);
            showSuccess(t`Redirecting to Stripe...`);
            window.location.href = String(stripeDetails?.connect_url);
        }
    }, [fetchStripeDetails, stripeDetailsQuery.isFetched]);

    if (error?.response?.status === 403) {
        return (
            <Card>
                <Group gap="xs" mb="md">
                    <ThemeIcon size="lg" radius="md" variant="light">
                        <IconAlertCircle size={20}/>
                    </ThemeIcon>
                    <Title order={2}>{t`Access Denied`}</Title>
                </Group>
                <Text size="md">
                    {error?.response?.data?.message}
                </Text>
            </Card>
        );
    }

    return (
        <div className={paymentClasses.stripeInfo}>
            <Title mb={10} order={3}>{t`Payment Processing`}</Title>

            {stripeDetails?.is_connect_setup_complete ? (
                <>
                    <Group gap="xs" mb="md">
                        <ThemeIcon size="sm" variant="light" radius="xl" color="green">
                            <IconCheck size={16}/>
                        </ThemeIcon>
                        <Text size="sm" fw={500}>
                            <b>{t`Connected to Stripe`}</b>
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
                                <Text span>{t`Open Stripe Dashboard`}</Text>
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
                            onClick={handleSetupStripe}
                        >
                            {(!isReturningFromStripe && !account?.stripe_account_id) && t`Connect with Stripe`}
                            {(isReturningFromStripe || account?.stripe_account_id) && t`Finish Stripe Setup`}
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

// Main Component that decides which to show
const ConnectStatus = ({account}: { account: Account }) => {
    if (isHiEvents()) {
        return <HiEventsConnectStatus account={account}/>;
    } else {
        return <OpenSourceConnectStatus account={account}/>;
    }
};

const PaymentSettings = () => {
    const accountQuery = useGetAccount();
    const stripeAccountsQuery = useGetStripeConnectAccounts(
        accountQuery.data?.id || 0,
        {
            enabled: !!accountQuery.data?.id
        }
    );

    return (
        <>
            <HeadingCard
                heading={t`Payment Settings`}
                subHeading={t`Manage your payment processing and view platform fees`}
            />

            {/* Migration Notice - Show at the top for Hi.Events users who need to migrate */}
            {isHiEvents() && stripeAccountsQuery.data && <MigrationNotice stripeData={stripeAccountsQuery.data}/>}

            <Card className={classes.tabContent}>
                <LoadingMask/>
                {(accountQuery.data) && (
                    <Grid gutter="xl">
                        <Grid.Col span={{base: 12, md: 6}}>
                            {accountQuery.isFetched && (
                                <ConnectStatus account={accountQuery.data}/>
                            )}
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
