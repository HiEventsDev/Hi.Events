import {t, Trans} from "@lingui/macro";
import {Badge, Button, Container, NumberInput} from "@mantine/core";
import {useState} from "react";
import {useNavigate, useParams} from "react-router";
import {useGetEventPublic} from "../../../../queries/useGetEventPublic.ts";
import {useGetPublicCashlessWallet} from "../../../../queries/useGetPublicCashlessWallet.ts";
import {useCreatePublicCashlessTopup} from "../../../../mutations/useCreatePublicCashlessTopup.ts";
import {HomepageInfoMessage} from "../../../common/HomepageInfoMessage";
import {PoweredByFooter} from "../../../common/PoweredByFooter";
import {formatCurrency} from "../../../../utilites/currency.ts";
import {showError} from "../../../../utilites/notifications.tsx";
import {CashlessTransactionHistory} from "./CashlessTransactionHistory";
import classes from './CashlessWallet.module.scss';

const SUGGESTED_MULTIPLIERS = [1, 2, 4, 10];

const CashlessWallet = () => {
    const {eventId, ticketReference} = useParams();
    const navigate = useNavigate();
    const {data: event, isError: eventError} = useGetEventPublic(eventId);
    const {data: wallet, isError: walletError} = useGetPublicCashlessWallet(eventId, ticketReference);
    const topupMutation = useCreatePublicCashlessTopup();

    const minimumAmount = event?.settings?.cashless_min_topup_amount ?? 5;
    const onlineTopupEnabled = event?.settings?.cashless_online_topup_enabled ?? true;
    const [amount, setAmount] = useState<number | string>(minimumAmount * 2);

    if (eventError || walletError) {
        return (
            <HomepageInfoMessage
                status="not_found"
                message={t`Balance not found`}
                subtitle={t`We couldn't find a cashless balance for this ticket. The link may have expired.`}
            />
        );
    }

    if (!event || !wallet) {
        return null;
    }

    if (!event.settings?.cashless_enabled) {
        return (
            <HomepageInfoMessage
                status="not_found"
                message={t`Cashless is not available`}
                subtitle={t`This event is not using cashless payments.`}
            />
        );
    }

    const isClosed = wallet.status === 'CLOSED';

    const handleTopup = () => {
        if (Number(amount) < minimumAmount) {
            showError(t`The minimum top-up is ${formatCurrency(minimumAmount, wallet.currency)}`);
            return;
        }

        topupMutation.mutate({eventId, ticketReference, amount: Number(amount)}, {
            onSuccess: ({data}) => navigate(`/checkout/${eventId}/${data.short_id}/details`),
            onError: (error: any) => showError(
                error?.response?.data?.message || t`We couldn't start your top-up. Please try again.`
            ),
        });
    };

    return (
        <Container className={classes.container}>
            <h2 className={classes.title}>{t`Your cashless balance`}</h2>
            <p className={classes.subtitle}>{event.title}</p>

            <div className={classes.balanceCard}>
                <span className={classes.balanceLabel}>{t`Available to spend`}</span>
                <span className={classes.balance}>{formatCurrency(wallet.balance, wallet.currency)}</span>
                {wallet.status === 'FROZEN' && (
                    <Badge color="orange" variant="light" mt="sm">{t`Frozen`}</Badge>
                )}
                {wallet.status === 'CLOSED' && (
                    <Badge color="gray" variant="light" mt="sm">{t`Closed`}</Badge>
                )}
                <span className={classes.ticketId}>{wallet.attendee_public_id}</span>
            </div>

            <p className={classes.explainer}>
                <Trans>
                    Show the QR code on your ticket at any bar or stand and the amount is taken straight from this
                    balance — no cash, no card.
                </Trans>
            </p>

            {isClosed && (
                <div className={classes.topupCard}>
                    <h3 className={classes.sectionTitle}>{t`Cashless is closed`}</h3>
                    <p>{t`Cashless is closed for this event, so this balance can no longer be used or topped up.`}</p>
                </div>
            )}

            {!isClosed && !onlineTopupEnabled && (
                <div className={classes.topupCard}>
                    <h3 className={classes.sectionTitle}>{t`Add funds`}</h3>
                    <p>{t`Top-ups are taken at the event. Show your ticket QR code at any top-up point.`}</p>
                </div>
            )}

            {!isClosed && onlineTopupEnabled && (
            <div className={classes.topupCard}>
                <h3 className={classes.sectionTitle}>{t`Add funds`}</h3>

                <div className={classes.suggestions}>
                    {SUGGESTED_MULTIPLIERS.map((multiplier) => {
                        const suggestion = minimumAmount * multiplier;
                        return (
                            <button
                                key={multiplier}
                                type="button"
                                className={`${classes.suggestion} ${Number(amount) === suggestion ? classes.suggestionActive : ''}`}
                                onClick={() => setAmount(suggestion)}
                            >
                                {formatCurrency(suggestion, wallet.currency)}
                            </button>
                        );
                    })}
                </div>

                <NumberInput
                    label={t`Or choose an amount`}
                    min={minimumAmount}
                    decimalScale={2}
                    value={amount}
                    onChange={setAmount}
                    data-testid="cashless-topup-amount-input"
                />

                <Button
                    fullWidth
                    mt="md"
                    size="md"
                    loading={topupMutation.isPending}
                    onClick={handleTopup}
                    data-testid="cashless-topup-button"
                >
                    {t`Top up ${formatCurrency(Number(amount) || 0, wallet.currency)}`}
                </Button>
            </div>
            )}

            <CashlessTransactionHistory
                transactions={wallet.transactions ?? []}
                currency={wallet.currency}
            />

            <PoweredByFooter/>
        </Container>
    );
};

export default CashlessWallet;
