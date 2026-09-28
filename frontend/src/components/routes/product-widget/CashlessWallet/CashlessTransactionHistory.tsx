import {t} from "@lingui/macro";
import {CashlessTransaction, CashlessTransactionType} from "../../../../types.ts";
import {formatCurrency} from "../../../../utilites/currency.ts";
import classes from './CashlessWallet.module.scss';

interface CashlessTransactionHistoryProps {
    transactions: CashlessTransaction[];
    currency: string;
}

const describe = (transaction: CashlessTransaction): string => {
    if (transaction.items?.length) {
        return transaction.items.map((item) => `${item.quantity} × ${item.product_title}`).join(', ');
    }

    return ({
        TOPUP_ONLINE: t`Top-up`,
        TOPUP_STAFF: t`Top-up at the event`,
        PURCHASE: t`Purchase`,
        REVERSAL: t`Cancelled`,
        REFUND_REMAINING: t`Balance refunded`,
        CLOSURE: t`Balance closed`,
    } as Record<CashlessTransactionType, string>)[transaction.type];
};

export const CashlessTransactionHistory = ({transactions, currency}: CashlessTransactionHistoryProps) => {
    if (transactions.length === 0) {
        return null;
    }

    return (
        <div className={classes.historyCard}>
            <h3 className={classes.sectionTitle}>{t`Your activity`}</h3>

            <ul className={classes.history}>
                {transactions.map((transaction) => (
                    <li key={transaction.short_id} className={classes.historyItem}>
                        <div>
                            <span className={classes.historyLabel}>{describe(transaction)}</span>
                            {transaction.sales_point_name && (
                                <span className={classes.historyMeta}>{transaction.sales_point_name}</span>
                            )}
                        </div>
                        <span className={transaction.amount < 0 ? classes.amountOut : classes.amountIn}>
                            {formatCurrency(transaction.amount, currency)}
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
};
