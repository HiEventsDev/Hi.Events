import {Alert, Button} from "@mantine/core";
import {t, Trans} from "@lingui/macro";
import {IconUsers, IconWheelchair, IconX} from "@tabler/icons-react";
import {BoxOfficeCart, CartLine, openSeatCount} from "../../../hooks/useBoxOfficeCart.ts";
import {formatCurrency} from "../../../../../utilites/currency.ts";
import classes from "./tabs/SellTab.module.scss";

interface CartPanelProps {
    cart: BoxOfficeCart;
    currency: string;
    allowDiscounts: boolean;
    allowPriceOverride: boolean;
    isCharging: boolean;
    onCharge: () => void;
    questionsPending: boolean;
    onOpenBuyer: () => void;
    onOpenDiscount: () => void;
    onOpenOverride: (line: CartLine) => void;
    seatsToChoose: number;
    seatDetails?: (seatUid: string) => SeatDetails;
    onRemoveSeat: (seatUid: string) => void;
}

export interface SeatDetails {
    label: string;
    note: string | null;
    accessible: boolean;
    companion: boolean;
}

export const CartPanel = ({
                              cart,
                              currency,
                              allowDiscounts,
                              allowPriceOverride,
                              isCharging,
                              onCharge,
                              questionsPending,
                              seatsToChoose,
                              seatDetails,
                              onRemoveSeat,
                              onOpenBuyer,
                              onOpenDiscount,
                              onOpenOverride,
                          }: CartPanelProps) => {
    const {lines, priceLookup, lineTotals, totals, state} = cart;
    const buyerLabel = [state.buyer.first_name, state.buyer.last_name].filter(Boolean).join(' ') || state.buyer.email;

    return (
        <div className={classes.panel}>
            <div className={classes.panelHeader}>
                <span>{t`Sale`}</span>
                {lines.length > 0 && (
                    <button type="button" className={classes.rowLink} onClick={cart.reset}>{t`Clear`}</button>
                )}
            </div>

            {lines.length === 0 && <div className={classes.empty}>{t`Tap a product to start a sale`}</div>}

            {lines.map(line => {
                const entry = priceLookup.get(line.priceId);
                if (!entry) return null;
                const title = entry.product.type === 'TIERED' && entry.price.label
                    ? `${entry.product.title} · ${entry.price.label}`
                    : entry.product.title;
                const open = openSeatCount(line);
                return (
                    <div key={line.priceId}>
                        <button
                            type="button"
                            className={classes.line}
                            data-editable={allowPriceOverride}
                            onClick={() => allowPriceOverride && onOpenOverride(line)}
                            data-testid={`box-office-cart-line-${line.priceId}`}
                        >
                            <span className={classes.lineQty}>{line.quantity}×</span>
                            <span className={classes.lineMain}>
                                {title}
                                {line.overridePrice !== undefined && (
                                    <div className={classes.lineSub}>{t`Price changed`}</div>
                                )}
                            </span>
                            <span className={classes.lineTotal}>{formatCurrency(lineTotals.get(line.priceId) ?? 0, currency)}</span>
                        </button>
                        {line.seatUids && seatDetails && (
                            <div className={classes.seatChips} data-testid={`box-office-cart-seats-${line.priceId}`}>
                                {line.seatUids.map((uid, position) => {
                                    const seat = seatDetails(uid);
                                    return (
                                        <span key={`${uid}-${position}`} className={classes.seatChip}>
                                            {seat.accessible && <IconWheelchair size={13} aria-label={t`Wheelchair space`}/>}
                                            {seat.companion && <IconUsers size={13} aria-label={t`Companion seat`}/>}
                                            {seat.label}
                                            {seat.note && <span className={classes.seatChipNote}>{seat.note}</span>}
                                            <button type="button" className={classes.seatChipRemove} onClick={() => onRemoveSeat(uid)}
                                                    aria-label={t`Remove seat ${seat.label}`}
                                                    data-testid={`box-office-seat-chip-remove-${uid}`}>
                                                <IconX size={12}/>
                                            </button>
                                        </span>
                                    );
                                })}
                                {open > 0 && (
                                    <span className={classes.seatChipOpen}>
                                        {open === 1 ? t`1 seat to choose` : <Trans>{open} seats to choose</Trans>}
                                    </span>
                                )}
                            </div>
                        )}
                    </div>
                );
            })}

            {lines.length > 0 && allowDiscounts && (
                <div className={classes.row}>
                    {state.discount ? (
                        <>
                            <span>
                                {t`Discount`}{' '}
                                {state.discount.type === 'PERCENTAGE' ? `(${state.discount.value}%)` : ''}
                            </span>
                            <span>
                                −{formatCurrency(totals.discountAmount, currency)}{' '}
                                <button type="button" className={classes.rowLink} aria-label={t`Remove discount`}
                                        onClick={() => cart.setDiscount(null)}>
                                    <IconX size={14}/>
                                </button>
                            </span>
                        </>
                    ) : (
                        <button type="button" className={classes.rowLink} onClick={onOpenDiscount}
                                data-testid="box-office-discount-button">{t`Add discount`}</button>
                    )}
                </div>
            )}

            {lines.length > 0 && (
                <div className={classes.row}>
                    {questionsPending ? (
                        <span className={classes.lineSub} style={{color: '#c92a2a'}}>{t`Required questions not answered`}</span>
                    ) : buyerLabel ? (
                        <span>{buyerLabel}</span>
                    ) : (
                        <span className={classes.lineSub}>{t`No buyer details`}</span>
                    )}
                    <button type="button" className={classes.rowLink} onClick={onOpenBuyer} data-testid="box-office-buyer-button">
                        {questionsPending ? t`Answer questions` : buyerLabel ? t`Edit` : t`Add buyer details`}
                    </button>
                </div>
            )}

            {state.serverErrors.cart && (
                <Alert color="red" variant="light">{state.serverErrors.cart}</Alert>
            )}

            {lines.length > 0 && (
                <>
                    <div className={classes.totalRow}>
                        <span className={classes.totalLabel}>{t`Estimated total`}</span>
                        <span className={classes.totalValue}>{formatCurrency(totals.total, currency)}</span>
                    </div>
                    <Button
                        size="lg"
                        fullWidth
                        loading={isCharging}
                        disabled={seatsToChoose > 0}
                        onClick={onCharge}
                        data-testid="box-office-charge-button"
                    >
                        {seatsToChoose > 0
                            ? (seatsToChoose === 1 ? t`Choose 1 more seat` : <Trans>Choose {seatsToChoose} more seats</Trans>)
                            : <Trans>Charge {formatCurrency(totals.total, currency)}</Trans>}
                    </Button>
                </>
            )}
        </div>
    );
};
