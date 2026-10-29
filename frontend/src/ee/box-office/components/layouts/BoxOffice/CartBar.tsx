import {Button} from "@mantine/core";
import {t, Trans} from "@lingui/macro";
import {formatCurrency} from "../../../../../utilites/currency.ts";
import classes from "./tabs/SellTab.module.scss";

interface CartBarProps {
    itemCount: number;
    total: number;
    currency: string;
    isCharging: boolean;
    seatsToChoose: number;
    onOpen: () => void;
    onCharge: () => void;
}

export const CartBar = ({itemCount, total, currency, isCharging, seatsToChoose, onOpen, onCharge}: CartBarProps) => {
    if (itemCount === 0) return null;

    return (
        <div className={classes.cartBar}>
            <button type="button" className={classes.cartBarSummary} onClick={onOpen} aria-label={t`View sale`}>
                <span className={classes.cartBarItems}>
                    {itemCount === 1 ? t`1 item` : <Trans>{itemCount} items</Trans>}
                </span>
                <span className={classes.cartBarTotal}>{formatCurrency(total, currency)}</span>
            </button>
            <Button size="md" loading={isCharging} disabled={seatsToChoose > 0} onClick={onCharge} data-testid="box-office-charge-button">
                {seatsToChoose > 0 ? <Trans>{seatsToChoose} to seat</Trans> : t`Charge`}
            </Button>
        </div>
    );
};
