import {Radio} from "@mantine/core";
import {t} from "@lingui/macro";
import {SubscriptionBillingInterval, SubscriptionPrice} from "../../../types.ts";
import {getClientLocale, toIntlLocale} from "../../../locales.ts";
import {minorUnitFactor} from "../../../utilites/currency.ts";
import classes from "./BrandingUpsell.module.scss";

interface PlanIntervalPickerProps {
    value: SubscriptionBillingInterval;
    onChange: (interval: SubscriptionBillingInterval) => void;
    prices: SubscriptionPrice[];
}

const formatPrice = (price: SubscriptionPrice) => {
    const amount = price.unit_amount / minorUnitFactor(price.currency);

    return new Intl.NumberFormat(toIntlLocale(getClientLocale()), {
        style: 'currency',
        currency: price.currency,
        minimumFractionDigits: Number.isInteger(amount) ? 0 : 2,
    }).format(amount);
};

const yearlySavingPercent = (monthly?: SubscriptionPrice, yearly?: SubscriptionPrice) => {
    if (!monthly || !yearly || monthly.currency !== yearly.currency) {
        return null;
    }
    const saving = 1 - yearly.unit_amount / (monthly.unit_amount * 12);

    return saving > 0 ? Math.round(saving * 100) : null;
};

export const defaultBillingInterval = (prices: SubscriptionPrice[]): SubscriptionBillingInterval =>
    prices.some(price => price.interval === 'year') ? 'year' : (prices[0]?.interval ?? 'year');

export const PlanIntervalPicker = ({value, onChange, prices}: PlanIntervalPickerProps) => {
    const monthly = prices.find(price => price.interval === 'month');
    const yearly = prices.find(price => price.interval === 'year');
    const saving = yearlySavingPercent(monthly, yearly);

    const options = [
        {
            value: 'month' as const,
            label: t`Monthly`,
            price: monthly && formatPrice(monthly),
            period: t`per month`,
            note: t`Cancel anytime`,
        },
        {
            value: 'year' as const,
            label: t`Yearly`,
            price: yearly && formatPrice(yearly),
            period: t`per year`,
            note: saving ? t`Save ${saving}% vs monthly` : t`Billed once a year`,
        },
    ].filter(option => option.price);

    return (
        <Radio.Group
            value={value}
            onChange={(interval) => onChange(interval as SubscriptionBillingInterval)}
            aria-label={t`Billing period`}
        >
            <div className={classes.intervals}>
                {options.map(option => (
                    <Radio.Card
                        key={option.value}
                        value={option.value}
                        className={classes.interval}
                    >
                        <span className={classes.intervalHeader}>
                            <span className={classes.intervalLabel}>{option.label}</span>
                            <Radio.Indicator size="xs"/>
                        </span>
                        <span className={classes.intervalPrice}>
                            <span className={classes.amount}>{option.price}</span>
                            <span className={classes.period}>{option.period}</span>
                        </span>
                        <span className={classes.intervalNote} data-highlight={option.value === 'year' && !!saving || undefined}>
                            {option.note}
                        </span>
                    </Radio.Card>
                ))}
            </div>
        </Radio.Group>
    );
};
