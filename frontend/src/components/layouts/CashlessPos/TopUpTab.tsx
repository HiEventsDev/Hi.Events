import {t} from "@lingui/macro";
import {Button, NumberInput, Select, TextInput} from "@mantine/core";
import {useState} from "react";
import {CashlessQuote, CashlessSalesPointPublic, CashlessStaffPaymentMethod, CashlessWalletPublic} from "../../../types.ts";
import {TicketScanZone, ScanMode} from "../../common/TicketScanZone";
import {formatCurrency} from "../../../utilites/currency.ts";
import classes from "./CashlessPos.module.scss";

interface TopUpTabProps {
    salesPoint: CashlessSalesPointPublic;
    wallet: CashlessWalletPublic | null;
    isSubmitting: boolean;
    onScan: (attendeePublicId: string) => void;
    onManualLookup: (attendeePublicId: string) => void;
    onClear: () => void;
    onTopUp: (amount: number, paymentMethod: CashlessStaffPaymentMethod) => void;
    scannerResetToken: number;
    quote: CashlessQuote | null;
    onAmountChange: (amount: number) => void;
    scanMode: ScanMode;
    onScanModeChange: (mode: ScanMode) => void;
    hidBuffer: string;
    hidPageHasFocus: boolean;
    isSoundOn: boolean;
    onSoundToggle: () => void;
}

export const TopUpTab = ({
                             salesPoint,
                             wallet,
                             isSubmitting,
                             onScan,
                             onManualLookup,
                             onClear,
                             onTopUp,
                             scannerResetToken,
                             quote,
                             onAmountChange,
                             scanMode,
                             onScanModeChange,
                             hidBuffer,
                             hidPageHasFocus,
                             isSoundOn,
                             onSoundToggle,
                         }: TopUpTabProps) => {
    const [manualId, setManualId] = useState('');
    const [amount, setAmount] = useState<number | string>(20);
    const [paymentMethod, setPaymentMethod] = useState<CashlessStaffPaymentMethod>('CASH');
    const currency = salesPoint.currency ?? 'USD';

    if (!salesPoint.allow_staff_topups) {
        return (
            <div className={classes.emptyState}>
                <p>{t`This sales point is not allowed to top up balances.`}</p>
            </div>
        );
    }

    return (
        <div className={classes.topUpLayout}>
            {!wallet && (
                <>
                    <TicketScanZone
                        mode={scanMode}
                        onModeChange={onScanModeChange}
                        hidPageHasFocus={hidPageHasFocus}
                        hidBuffer={hidBuffer}
                        isSoundOn={isSoundOn}
                        onSoundToggle={onSoundToggle}
                        onCodeScanned={onScan}
                        scannerResetToken={scannerResetToken}
                        listeningLabel={t`Scan a ticket to top up its balance`}
                        pausedLabel={t`Tap this screen to resume scanning`}
                    />

                    <form
                        className={classes.manualLookup}
                        onSubmit={(event) => {
                            event.preventDefault();
                            if (manualId.trim()) {
                                onManualLookup(manualId.trim());
                                setManualId('');
                            }
                        }}
                    >
                        <TextInput
                            placeholder={t`Or type the ticket ID`}
                            value={manualId}
                            onChange={(event) => setManualId(event.currentTarget.value)}
                            data-testid="cashless-pos-topup-ticket-input"
                        />
                        <Button type="submit" variant="default">{t`Find`}</Button>
                    </form>
                </>
            )}

            {wallet && (
                <div className={classes.topUpForm}>
                    <div className={classes.customerCard}>
                        <span className={classes.customerName}>{wallet.attendee_name}</span>
                        <span className={classes.customerTicket}>{wallet.attendee_public_id}</span>
                        <span className={classes.customerBalance}>{formatCurrency(wallet.balance, currency)}</span>
                        {wallet.status === 'FROZEN' && (
                            <span className={classes.customerWarning}>{t`This balance is frozen`}</span>
                        )}
                        {wallet.status === 'CLOSED' && (
                            <span className={classes.customerWarning}>{t`This balance is closed`}</span>
                        )}
                        <Button variant="subtle" size="compact-sm" onClick={onClear}>
                            {t`Serve someone else`}
                        </Button>
                    </div>

                    <NumberInput
                        label={t`Amount to add`}
                        size="lg"
                        min={0.01}
                        decimalScale={2}
                        value={amount}
                        onChange={(value) => {
                            setAmount(value);
                            onAmountChange(Number(value) || 0);
                        }}
                        data-testid="cashless-pos-topup-amount-input"
                    />

                    <Select
                        label={t`How did they pay?`}
                        size="lg"
                        value={paymentMethod}
                        onChange={(value) => setPaymentMethod((value ?? 'CASH') as CashlessStaffPaymentMethod)}
                        data={[
                            {value: 'CASH', label: t`Cash`},
                            {value: 'CARD_TERMINAL', label: t`Card terminal`},
                            {value: 'OTHER', label: t`Other`},
                        ]}
                    />

                    {paymentMethod !== 'CASH' && !!quote?.fees && (
                        <div className={classes.terminalTotal}>
                            <span>{t`Take on the card terminal`}</span>
                            <strong data-testid="cashless-pos-terminal-total">
                                {formatCurrency(quote.total, currency)}
                            </strong>
                            <span className={classes.terminalNote}>
                                {t`${formatCurrency(Number(amount) || 0, currency)} onto the ticket plus ${formatCurrency(quote.fees + quote.taxes, currency)} in fees`}
                            </span>
                        </div>
                    )}

                    <Button
                        size="lg"
                        mt="md"
                        fullWidth
                        loading={isSubmitting}
                        disabled={Number(amount) <= 0 || wallet.status !== 'ACTIVE'}
                        onClick={() => onTopUp(Number(amount), paymentMethod)}
                        data-testid="cashless-pos-topup-submit-button"
                    >
                        {t`Add ${formatCurrency(Number(amount) || 0, currency)}`}
                    </Button>
                </div>
            )}
        </div>
    );
};
