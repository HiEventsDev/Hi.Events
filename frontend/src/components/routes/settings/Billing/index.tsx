import {useEffect, useRef, useState} from "react";
import {useSearchParams} from "react-router";
import {Alert, Button, Skeleton} from "@mantine/core";
import {t} from "@lingui/macro";
import {useBrandingSubscription} from "../../../../hooks/useBrandingSubscription.ts";
import {useSubscriptionRedirect} from "../../../../hooks/useSubscriptionRedirect.ts";
import {AccountSubscription, AccountSubscriptionStatus, SubscriptionBillingInterval} from "../../../../types.ts";
import {formatDateWithLocale} from "../../../../utilites/dates.ts";
import {showSuccess} from "../../../../utilites/notifications.tsx";
import {StatusDot} from "../../../common/StatusDot";
import {SettingsFooter, SettingsPage, SettingsRow, SettingsSection} from "../../../common/SettingsPage";
import {
    BrandingAnimation,
    defaultBillingInterval,
    IncludedFeatures,
    PlanIntervalPicker,
} from "../../../common/BrandingUpsell";
import classes from "./Billing.module.scss";

const CONFIRMATION_POLL_MS = 2000;
const CONFIRMATION_TIMEOUT_MS = 30000;

const ENDED_STATUSES: AccountSubscriptionStatus[] = ['CANCELED', 'INCOMPLETE_EXPIRED'];

const isOpen = (subscription: AccountSubscription) =>
    subscription.status !== null && !ENDED_STATUSES.includes(subscription.status);

const SubscriptionStatus = ({subscription}: { subscription: AccountSubscription }) => {
    switch (subscription.status) {
        case 'PAST_DUE':
        case 'UNPAID':
            return <StatusDot tone="warning" strong>{t`Payment failed`}</StatusDot>;
        case 'INCOMPLETE':
            return <StatusDot tone="warning">{t`Payment processing`}</StatusDot>;
        case 'PAUSED':
            return <StatusDot tone="muted">{t`Paused`}</StatusDot>;
        case 'TRIALING':
            return <StatusDot tone="success">{t`Trial`}</StatusDot>;
    }

    if (subscription.cancel_at_period_end) {
        return <StatusDot tone="warning">{t`Cancelling`}</StatusDot>;
    }

    return <StatusDot tone="success">{t`Active`}</StatusDot>;
};

const Billing = () => {
    const [searchParams, setSearchParams] = useSearchParams();
    const [billingInterval, setBillingInterval] = useState<SubscriptionBillingInterval | null>(null);
    const [awaitingConfirmation, setAwaitingConfirmation] = useState(false);
    const [confirmationTimedOut, setConfirmationTimedOut] = useState(false);
    const successHandled = useRef(false);
    const {account, subscription, subscriptionQuery, prices, canManageBilling} = useBrandingSubscription({refreshFromStripe: true});
    const {startCheckout, openPortal, isStartingCheckout, isOpeningPortal} = useSubscriptionRedirect(account?.id);

    useEffect(() => {
        if (searchParams.get('checkout') !== 'success' || successHandled.current) {
            return;
        }

        successHandled.current = true;
        showSuccess(t`Thanks for subscribing. Your branding changes apply as soon as Stripe confirms the payment.`);
        setAwaitingConfirmation(true);
        setSearchParams(params => {
            params.delete('checkout');
            return params;
        }, {replace: true});
    }, [searchParams]);

    useEffect(() => {
        if (!awaitingConfirmation) {
            return;
        }
        if (subscription?.active) {
            setAwaitingConfirmation(false);
            return;
        }

        const poll = window.setInterval(() => subscriptionQuery.refetch(), CONFIRMATION_POLL_MS);
        const stop = window.setTimeout(() => {
            setAwaitingConfirmation(false);
            setConfirmationTimedOut(true);
        }, CONFIRMATION_TIMEOUT_MS);

        return () => {
            window.clearInterval(poll);
            window.clearTimeout(stop);
        };
    }, [awaitingConfirmation, subscription?.active]);

    const timezone = account?.timezone ?? 'UTC';
    const periodEnd = subscription?.current_period_end
        ? formatDateWithLocale(subscription.current_period_end, 'shortDate', timezone)
        : null;

    const content = () => {
        if (subscriptionQuery.isError) {
            return (
                <Alert color="red" variant="light">
                    {t`We couldn't load your billing details. Please refresh the page to try again.`}
                </Alert>
            );
        }

        if (!subscription) {
            return <Skeleton height={320} radius="md"/>;
        }

        if (!subscription.available || !canManageBilling) {
            return null;
        }

        if (isOpen(subscription)) {
            return (
                <SettingsSection title={t`Subscription`}>
                    <SettingsRow label={t`Status`} inlineControl>
                        <SubscriptionStatus subscription={subscription}/>
                    </SettingsRow>
                    {periodEnd && subscription.active && (
                        <SettingsRow
                            label={subscription.cancel_at_period_end ? t`Ends on` : t`Renews on`}
                            description={subscription.cancel_at_period_end
                                ? t`Hi.Events branding returns after this date. You can resume the subscription before then.`
                                : undefined}
                            inlineControl
                        >
                            <span className={classes.value}>{periodEnd}</span>
                        </SettingsRow>
                    )}
                    {(subscription.status === 'PAST_DUE' || subscription.status === 'UNPAID') && (
                        <SettingsRow
                            label={t`Update your payment method`}
                            description={t`Your last payment didn't go through. Update your card to keep your branding removed.`}
                        />
                    )}
                    {subscription.status === 'INCOMPLETE' && (
                        <SettingsRow
                            label={t`Your payment is still processing`}
                            description={t`Some payment methods take a little while to confirm. Your branding is removed as soon as Stripe confirms it.`}
                        />
                    )}
                    <SettingsRow label={t`Included`} below={<IncludedFeatures/>}/>
                    <SettingsFooter hint={t`Change your card, switch between monthly and yearly billing, download invoices or cancel.`}>
                        <Button onClick={openPortal} loading={isOpeningPortal}>
                            {t`Manage billing`}
                        </Button>
                    </SettingsFooter>
                </SettingsSection>
            );
        }

        const selectedInterval = billingInterval ?? defaultBillingInterval(prices);

        return (
            <SettingsSection title={t`Remove Hi.Events branding`}>
                <div className={classes.hero}>
                    <BrandingAnimation/>
                    <div className={classes.heroText}>
                        <p className={classes.heroLead}>
                            {awaitingConfirmation
                                ? t`Waiting for Stripe to confirm your payment…`
                                : t`Applies to every organizer and event in this account.`}
                        </p>
                        <IncludedFeatures/>
                    </div>
                </div>
                {confirmationTimedOut && (
                    <SettingsRow
                        label={t`Your payment is still processing`}
                        description={t`Stripe hasn't confirmed your payment yet. Refresh this page in a few minutes before trying again.`}
                    />
                )}
                <SettingsRow
                    label={t`Billing period`}
                    below={<PlanIntervalPicker value={selectedInterval} onChange={setBillingInterval} prices={prices}/>}
                />
                <SettingsFooter hint={t`You'll pay securely with Stripe. Cancel anytime.`}>
                    {subscription.status !== null && (
                        <Button variant="subtle" color="gray" onClick={openPortal} loading={isOpeningPortal}>
                            {t`Past invoices`}
                        </Button>
                    )}
                    <Button
                        onClick={() => startCheckout(selectedInterval)}
                        loading={isStartingCheckout}
                        disabled={awaitingConfirmation || confirmationTimedOut}
                    >
                        {t`Continue to payment`}
                    </Button>
                </SettingsFooter>
            </SettingsSection>
        );
    };

    return (
        <SettingsPage
            title={t({message: 'Billing', context: 'Account subscription'})}
            description={t`Remove Hi.Events branding from everything your attendees see, and put your own logo in their emails.`}
        >
            {content()}
        </SettingsPage>
    );
};

export default Billing;
