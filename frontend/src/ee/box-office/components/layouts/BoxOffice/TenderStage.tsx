import {useState} from "react";
import {Button, NumberInput, TextInput} from "@mantine/core";
import {t, Trans} from "@lingui/macro";
import {IconArrowLeft, IconCash, IconCreditCard, IconGift, IconReceipt} from "@tabler/icons-react";
import {Order} from "../../../../../types.ts";
import {formatCurrency, getCurrencySymbol} from "../../../../../utilites/currency.ts";
import classes from "./tabs/SellTab.module.scss";

type TenderChoice = 'CASH' | 'COMP' | 'OTHER';

interface TenderStageProps {
    order: Order;
    isSubmitting: boolean;
    isAbandoning: boolean;
    cardAvailable: boolean;
    compAvailable: boolean;
    initialMode?: 'choose' | 'cash' | 'other';
    onBack: () => void;
    onTender: (tender: TenderChoice, extra?: { amount_tendered?: number; reference?: string }) => void;
    onCard: () => void;
}

const ZERO_DECIMAL_CURRENCIES = ['JPY', 'KRW', 'VND', 'CLP', 'ISK', 'HUF', 'TWD', 'UGX', 'XOF', 'XAF', 'XPF'];

const quickAmounts = (total: number, currency: string): number[] => {
    const denominations = ZERO_DECIMAL_CURRENCIES.includes(currency) ? [1000, 5000, 10000] : [5, 10, 20, 50, 100];
    const amounts = new Set<number>();
    denominations.forEach(d => {
        const rounded = Math.ceil(total / d) * d;
        if (rounded > total) amounts.add(rounded);
    });
    return [...amounts].sort((a, b) => a - b).slice(0, 3);
};

const TotalHeader = ({order}: { order: Order }) => (
    <div className={classes.stageTotal}>
        <div className={classes.stageTotalLabel}>{t`Total to pay`}</div>
        <div className={classes.stageTotalValue}>{formatCurrency(order.total_gross, order.currency)}</div>
    </div>
);

export const TenderStage = ({order, isSubmitting, isAbandoning, cardAvailable, compAvailable, initialMode = 'choose', onBack, onTender, onCard}: TenderStageProps) => {
    const [mode, setMode] = useState<'choose' | 'cash' | 'other' | 'comp'>(initialMode);
    const total = Number(order.total_gross);
    const currency = order.currency;
    const isFree = total <= 0;
    const [tendered, setTendered] = useState<number | string>(total);
    const [reference, setReference] = useState('');
    const tenderedNumber = Number(tendered) || 0;
    const change = tenderedNumber - total;

    const backButton = (
        <Button variant="subtle" color="gray" leftSection={<IconArrowLeft size={16}/>} loading={isAbandoning}
                onClick={() => mode === 'choose' ? onBack() : setMode('choose')}>
            {mode === 'choose' ? t`Back to sale` : t`Back`}
        </Button>
    );

    if (isFree) {
        return (
            <div className={classes.stageCard}>
                <TotalHeader order={order}/>
                <div className={classes.inlineStatus}>{t`Free order, no payment needed`}</div>
                <Button size="lg" fullWidth loading={isSubmitting} onClick={() => onTender('COMP')}
                        data-testid="box-office-free-complete-button">
                    {t`Complete sale`}
                </Button>
                {backButton}
            </div>
        );
    }

    if (mode === 'cash') {
        return (
            <div className={classes.stageCard}>
                <TotalHeader order={order}/>
                <NumberInput
                    label={t`Amount tendered`}
                    value={tendered}
                    onChange={setTendered}
                    min={0}
                    decimalScale={ZERO_DECIMAL_CURRENCIES.includes(currency) ? 0 : 2}
                    leftSection={getCurrencySymbol(currency)}
                    size="lg"
                    inputMode="decimal"
                    autoFocus
                    data-testid="box-office-cash-tendered"
                />
                <div className={classes.chips}>
                    <button type="button" className={classes.chipBtn} data-active={tenderedNumber === total}
                            onClick={() => setTendered(total)}>
                        {t`Exact`}
                    </button>
                    {quickAmounts(total, currency).map(amount => (
                        <button key={amount} type="button" className={classes.chipBtn} data-active={tenderedNumber === amount}
                                onClick={() => setTendered(amount)}>
                            {formatCurrency(amount, currency)}
                        </button>
                    ))}
                </div>
                <div className={classes.change}>
                    <div className={classes.changeLabel}>{change < 0 ? t`Still owed` : t`Change due`}</div>
                    <div className={classes.changeValue} data-short={change < 0}>
                        {formatCurrency(Math.abs(change), currency)}
                    </div>
                </div>
                <Button size="lg" fullWidth loading={isSubmitting} disabled={change < 0}
                        onClick={() => onTender('CASH', {amount_tendered: tenderedNumber})}
                        data-testid="box-office-cash-complete-button">
                    {t`Complete sale`}
                </Button>
                {backButton}
            </div>
        );
    }

    if (mode === 'comp') {
        return (
            <div className={classes.stageCard}>
                <TotalHeader order={order}/>
                <div className={classes.hero}>
                    <div className={classes.heroIcon}><IconGift size={30}/></div>
                    <h2 className={classes.heroTitle}>{t`Comp this order?`}</h2>
                    <p className={classes.heroSub}>{t`No payment will be recorded. The tickets are issued free of charge and the order total becomes zero.`}</p>
                </div>
                <Button size="lg" fullWidth loading={isSubmitting} onClick={() => onTender('COMP')}
                        data-testid="box-office-comp-confirm-button">
                    {t`Confirm comp`}
                </Button>
                {backButton}
            </div>
        );
    }

    if (mode === 'other') {
        return (
            <div className={classes.stageCard}>
                <TotalHeader order={order}/>
                <TextInput
                    label={t`Reference`}
                    description={t`Where it was paid, e.g. SumUp or bank transfer`}
                    required
                    value={reference}
                    onChange={(e) => setReference(e.currentTarget.value)}
                    maxLength={120}
                    size="md"
                    autoFocus
                />
                <Button size="lg" fullWidth loading={isSubmitting}
                        disabled={reference.trim() === ''}
                        onClick={() => onTender('OTHER', {reference: reference.trim()})}
                        data-testid="box-office-other-complete-button">
                    {t`Complete sale`}
                </Button>
                {backButton}
            </div>
        );
    }

    return (
        <div className={classes.stageCard}>
            <TotalHeader order={order}/>
            <div className={classes.tenderGrid}>
                <button type="button" className={`${classes.tenderBtn} ${classes.tenderBtnPrimary}`}
                        onClick={() => setMode('cash')} data-testid="box-office-tender-cash">
                    <IconCash size={26}/>
                    {t`Cash`}
                </button>
                {cardAvailable && (
                    <button type="button" className={`${classes.tenderBtn} ${classes.tenderBtnPrimary}`}
                            onClick={onCard} data-testid="box-office-tender-card">
                        <IconCreditCard size={26}/>
                        {t`Card`}
                    </button>
                )}
                {compAvailable && (
                    <button type="button" className={classes.tenderBtn} onClick={() => setMode('comp')}
                            data-testid="box-office-tender-comp">
                        <IconGift size={26}/>
                        {t`Comp`}
                    </button>
                )}
                <button type="button" className={classes.tenderBtn} onClick={() => setMode('other')}
                        data-testid="box-office-tender-other">
                    <IconReceipt size={26}/>
                    {t`Paid elsewhere`}
                </button>
            </div>
            <div className={classes.inlineStatus}>
                <Trans>Order {order.public_id}</Trans>
            </div>
            {backButton}
        </div>
    );
};
