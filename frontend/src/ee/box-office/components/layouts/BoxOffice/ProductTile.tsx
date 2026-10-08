import {IconMinus, IconPlus} from "@tabler/icons-react";
import {t, Trans} from "@lingui/macro";
import {BoxOfficeProduct, BoxOfficeProductPrice} from "../../../../../types.ts";
import {formatCurrency} from "../../../../../utilites/currency.ts";
import classes from "./tabs/SellTab.module.scss";

interface ProductTileProps {
    product: BoxOfficeProduct;
    price: BoxOfficeProductPrice;
    quantity: number;
    cap: number;
    currency: string;
    error?: string;
    seatHint?: string;
    onIncrement: () => void;
    onDecrement: () => void;
}

const LOW_STOCK_THRESHOLD = 20;

const visibilityNotes = (product: BoxOfficeProduct, price: BoxOfficeProductPrice): string[] => {
    const notes: string[] = [];
    if (product.is_hidden) notes.push(t`Hidden online`);
    if (product.is_hidden_without_promo_code) notes.push(t`Promo code only`);
    if (product.is_addon_only) notes.push(t`Add-on only`);
    if (price.off_sale_reason === 'BEFORE_SALE_START') notes.push(t`Not on sale yet`);
    if (price.off_sale_reason === 'AFTER_SALE_END') notes.push(t`Sale ended`);
    return notes;
};

export const ProductTile = ({product, price, quantity, cap, currency, error, seatHint, onIncrement, onDecrement}: ProductTileProps) => {
    const title = product.type === 'TIERED' && price.label ? `${product.title} · ${price.label}` : product.title;
    const soldOut = !price.is_available;
    const remaining = price.quantity_remaining;
    const canAdd = !soldOut && quantity < cap;
    const notes = visibilityNotes(product, price);

    return (
        <div
            className={classes.tile}
            data-selected={quantity > 0}
            data-disabled={soldOut}
            data-error={!!error}
            onClick={() => canAdd && onIncrement()}
            role="button"
            aria-label={title}
        >
            <div className={classes.tileMain}>
                <div className={classes.tileTitle}>{title}</div>
                <div className={classes.tileMeta}>
                    <span className={classes.tilePrice}>
                        {price.price_including_taxes_and_fees > 0
                            ? formatCurrency(price.price_including_taxes_and_fees, currency)
                            : t`Free`}
                    </span>
                    {soldOut && <span className={classes.tileWarn}>{t`Sold out`}</span>}
                    {seatHint && <span className={classes.tileSeatHint}>{seatHint}</span>}
                    {!seatHint && !soldOut && remaining !== null && remaining <= LOW_STOCK_THRESHOLD && (
                        <span className={classes.tileWarn}><Trans>{remaining} left</Trans></span>
                    )}
                </div>
                {notes.length > 0 && (
                    <div className={classes.tileNotes}>
                        {notes.map(note => <span key={note} className={classes.tileNote}>{note}</span>)}
                    </div>
                )}
                {error && <div className={classes.tileError}>{error}</div>}
            </div>
            <div className={classes.stepper} onClick={(e) => e.stopPropagation()}>
                <button
                    type="button"
                    className={classes.stepBtn}
                    onClick={onDecrement}
                    disabled={quantity === 0}
                    aria-label={t`Remove one`}
                    data-testid={`box-office-qty-minus-${price.id}`}
                >
                    <IconMinus size={20}/>
                </button>
                <span className={classes.stepValue}>{quantity}</span>
                <button
                    type="button"
                    className={`${classes.stepBtn} ${classes.stepBtnPrimary}`}
                    onClick={onIncrement}
                    disabled={!canAdd}
                    aria-label={t`Add one`}
                    data-testid={`box-office-qty-plus-${price.id}`}
                >
                    <IconPlus size={20}/>
                </button>
            </div>
        </div>
    );
};
