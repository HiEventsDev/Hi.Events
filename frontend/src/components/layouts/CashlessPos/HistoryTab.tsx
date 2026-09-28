import {t} from "@lingui/macro";
import {Button} from "@mantine/core";
import {IconArrowBackUp} from "@tabler/icons-react";
import {CashlessTransaction} from "../../../types.ts";
import {formatCurrency} from "../../../utilites/currency.ts";
import classes from "./CashlessPos.module.scss";

interface HistoryTabProps {
    transactions: CashlessTransaction[];
    currency: string;
    reversingShortId: string | null;
    onReverse: (transactionShortId: string) => void;
}

export const HistoryTab = ({transactions, currency, reversingShortId, onReverse}: HistoryTabProps) => {
    if (transactions.length === 0) {
        return (
            <div className={classes.emptyState}>
                <p>{t`Sales you take on this device will appear here.`}</p>
            </div>
        );
    }

    const reversedIds = new Set(transactions.map((transaction) => transaction.reverses_transaction_id));
    const isUndoable = (transaction: CashlessTransaction) =>
        (transaction.type === 'PURCHASE' || transaction.type === 'TOPUP_STAFF')
        && !reversedIds.has(transaction.id);

    const titleOf = (transaction: CashlessTransaction) => {
        if (transaction.type === 'REVERSAL') {
            return t`Cancelled`;
        }

        return transaction.items?.length
            ? transaction.items.map((item) => `${item.quantity} × ${item.product_title}`).join(', ')
            : t`Top-up`;
    };

    return (
        <ul className={classes.historyList}>
            {transactions.map((transaction) => (
                <li key={transaction.short_id} className={classes.historyRow}>
                    <div>
                        <span className={classes.historyTitle}>
                            {titleOf(transaction)}
                        </span>
                        <span className={classes.historySub}>{transaction.attendee_public_id}</span>
                    </div>

                    <span className={classes.historyAmount}>
                        {formatCurrency(transaction.amount, currency)}
                    </span>

                    {isUndoable(transaction) && (
                    <Button
                        variant="subtle"
                        size="compact-sm"
                        color="red"
                        leftSection={<IconArrowBackUp size={14}/>}
                        loading={reversingShortId === transaction.short_id}
                        onClick={() => onReverse(transaction.short_id)}
                    >
                        {t`Undo`}
                    </Button>
                    )}
                </li>
            ))}
        </ul>
    );
};
