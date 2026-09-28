import {t} from "@lingui/macro";
import {Button, TextInput} from "@mantine/core";
import {IconScan, IconTrash} from "@tabler/icons-react";
import {useState} from "react";
import {CashlessQuote, CashlessSalesPointPublic, CashlessWalletPublic, Product} from "../../../types.ts";
import {TicketScanZone, ScanMode} from "../../common/TicketScanZone";
import {formatCurrency} from "../../../utilites/currency.ts";
import classes from "./CashlessPos.module.scss";

export interface CartLine {
    product_id: number;
    product_price_id: number;
    title: string;
    unitPrice: number;
    quantity: number;
}

interface ChargeTabProps {
    salesPoint: CashlessSalesPointPublic;
    wallet: CashlessWalletPublic | null;
    scannedId: string;
    cart: CartLine[];
    isCharging: boolean;
    onScan: (attendeePublicId: string) => void;
    onManualLookup: (attendeePublicId: string) => void;
    onAddLine: (product: Product, priceId: number, title: string, unitPrice: number) => void;
    onChangeQuantity: (priceId: number, delta: number) => void;
    onClear: () => void;
    onCharge: () => void;
    scannerResetToken: number;
    quote: CashlessQuote | null;
    scanMode: ScanMode;
    onScanModeChange: (mode: ScanMode) => void;
    hidBuffer: string;
    hidPageHasFocus: boolean;
    isSoundOn: boolean;
    onSoundToggle: () => void;
}

export const ChargeTab = ({
                              salesPoint,
                              wallet,
                              scannedId,
                              cart,
                              isCharging,
                              onScan,
                              onManualLookup,
                              onAddLine,
                              onChangeQuantity,
                              onClear,
                              onCharge,
                              scannerResetToken,
                              quote,
                              scanMode,
                              onScanModeChange,
                              hidBuffer,
                              hidPageHasFocus,
                              isSoundOn,
                              onSoundToggle,
                          }: ChargeTabProps) => {
    const [manualId, setManualId] = useState('');
    const currency = salesPoint.currency ?? 'USD';
    const subtotal = cart.reduce((sum, line) => sum + (line.unitPrice * line.quantity), 0);
    const total = quote?.total ?? subtotal;
    const canCharge = !!wallet && cart.length > 0 && wallet.balance >= total && wallet.status === 'ACTIVE';

    return (
        <div className={`${classes.chargeLayout} ${wallet ? classes.chargeLayoutSplit : ''}`}>
            <section className={classes.customerPane}>
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
                            listeningLabel={t`Scan a ticket to look up its balance`}
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
                                leftSection={<IconScan size={16}/>}
                                data-testid="cashless-pos-ticket-input"
                            />
                            <Button type="submit" variant="default">{t`Find`}</Button>
                        </form>

                        {scannedId && (
                            <p className={classes.hint}>{t`Looking up ${scannedId}...`}</p>
                        )}
                    </>
                )}

                {wallet && (
                    <div className={classes.customerCard}>
                        <span className={classes.customerName}>{wallet.attendee_name}</span>
                        <span className={classes.customerTicket}>{wallet.attendee_public_id}</span>
                        <span className={classes.customerBalance}>
                            {formatCurrency(wallet.balance, currency)}
                        </span>
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
                )}
            </section>

            {wallet && (
                <section className={classes.catalogue}>
                    <div className={classes.productGrid}>
                        {salesPoint.products?.map((product) => (
                            product.prices?.map((price) => (
                                <button
                                    key={price.id}
                                    type="button"
                                    className={classes.productButton}
                                    onClick={() => onAddLine(
                                        product,
                                        Number(price.id),
                                        price.label ? `${product.title} - ${price.label}` : product.title,
                                        Number(price.price),
                                    )}
                                    data-testid={`cashless-pos-product-${price.id}`}
                                >
                                    <span className={classes.productTitle}>
                                        {price.label ? `${product.title} · ${price.label}` : product.title}
                                    </span>
                                    <span className={classes.productPrice}>
                                        {formatCurrency(Number(price.price), currency)}
                                    </span>
                                </button>
                            ))
                        ))}
                    </div>

                    <div className={classes.cart}>
                        {cart.length === 0 && (
                            <p className={classes.hint}>{t`Tap a product to start an order.`}</p>
                        )}

                        {cart.map((line) => (
                            <div key={line.product_price_id} className={classes.cartLine}>
                                <span className={classes.cartTitle}>{line.title}</span>
                                <div className={classes.quantityControls}>
                                    <button
                                        type="button"
                                        onClick={() => onChangeQuantity(line.product_price_id, -1)}
                                        aria-label={t`Remove one`}
                                    >
                                        −
                                    </button>
                                    <span>{line.quantity}</span>
                                    <button
                                        type="button"
                                        onClick={() => onChangeQuantity(line.product_price_id, 1)}
                                        aria-label={t`Add one`}
                                    >
                                        +
                                    </button>
                                </div>
                                <span className={classes.cartTotal}>
                                    {formatCurrency(line.unitPrice * line.quantity, currency)}
                                </span>
                            </div>
                        ))}

                        <div className={classes.cartFooter}>
                            {!!quote?.fees && (
                                <div className={classes.subtotalRow}>
                                    <span>{t`Subtotal`}</span>
                                    <span>{formatCurrency(quote.subtotal, currency)}</span>
                                </div>
                            )}
                            {!!quote?.fees && (
                                <div className={classes.subtotalRow}>
                                    <span>{t`Fees`}</span>
                                    <span>{formatCurrency(quote.fees, currency)}</span>
                                </div>
                            )}
                            {!!quote?.taxes && (
                                <div className={classes.subtotalRow}>
                                    <span>{t`Tax`}</span>
                                    <span>{formatCurrency(quote.taxes, currency)}</span>
                                </div>
                            )}
                            <div className={classes.totalRow}>
                                <span>{t`Total`}</span>
                                <strong>{formatCurrency(total, currency)}</strong>
                            </div>

                            {wallet && cart.length > 0 && wallet.balance < total && (
                                <p className={classes.insufficient}>
                                    {t`Not enough on this ticket. Short by ${formatCurrency(total - wallet.balance, currency)}.`}
                                </p>
                            )}

                            <div className={classes.cartActions}>
                                <Button
                                    variant="default"
                                    leftSection={<IconTrash size={16}/>}
                                    onClick={onClear}
                                    disabled={cart.length === 0 && !wallet}
                                >
                                    {t`Clear`}
                                </Button>
                                <Button
                                    size="lg"
                                    loading={isCharging}
                                    disabled={!canCharge}
                                    onClick={onCharge}
                                    data-testid="cashless-pos-charge-button"
                                >
                                    {t`Charge ${formatCurrency(total, currency)}`}
                                </Button>
                            </div>
                        </div>
                    </div>
                </section>
            )}
        </div>
    );
};
