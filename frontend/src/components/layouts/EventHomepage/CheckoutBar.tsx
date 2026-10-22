import {IconArrowRight, IconTicket} from "@tabler/icons-react";
import classes from "./EventHomepage.module.scss";

export type CheckoutBarMode = 'tickets' | 'continue';

interface CheckoutBarProps {
    mode: CheckoutBarMode | null;
    compactOnWide: boolean;
    priceLabel: string | null;
    dateLabel: string | null;
    getTicketsText: string;
    continueText: string;
    onGetTickets: () => void;
    onContinue: () => void;
}

export const CheckoutBar = ({mode, compactOnWide, priceLabel, dateLabel, getTicketsText, continueText, onGetTickets, onContinue}: CheckoutBarProps) => {
    const compact = compactOnWide || undefined;

    if (mode === 'continue') {
        return (
            <div className={classes.checkoutBar} data-mode="continue" data-compact-wide={compact}>
                <button type="button" className={classes.checkoutButton} onClick={onContinue} data-testid="floating-checkout-button">
                    <span className={classes.checkoutLabel}>{continueText}</span>
                    <IconArrowRight/>
                </button>
            </div>
        );
    }

    if (mode === 'tickets') {
        return (
            <div className={classes.checkoutBar} data-mode="tickets" data-compact-wide={compact}>
                {(priceLabel || dateLabel) && (
                    <div className={classes.checkoutSummary}>
                        {priceLabel && <span className={classes.checkoutPrice}>{priceLabel}</span>}
                        {dateLabel && <span className={classes.checkoutDate}>{dateLabel}</span>}
                    </div>
                )}
                <button type="button" className={classes.checkoutButton} onClick={onGetTickets}>
                    <IconTicket/>
                    <span className={classes.checkoutLabel}>{getTicketsText}</span>
                </button>
            </div>
        );
    }

    return null;
};
