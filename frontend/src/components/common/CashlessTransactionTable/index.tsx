import {t} from "@lingui/macro";
import {Badge, Table as MantineTable, Text} from "@mantine/core";
import {IconArrowBackUp} from "@tabler/icons-react";
import {useParams} from "react-router";
import {CashlessTransaction, CashlessTransactionType, IdParam} from "../../../types.ts";
import {NoResultsSplash} from "../NoResultsSplash";
import {Table, TableHead} from "../Table";
import {ActionMenu} from "../ActionMenu";
import {formatCurrency} from "../../../utilites/currency.ts";
import {prettyDate} from "../../../utilites/dates.ts";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";
import {confirmationDialog} from "../../../utilites/confirmationDialog.tsx";
import {useReverseCashlessTransaction} from "../../../mutations/useReverseCashlessTransaction.ts";
import classes from "./CashlessTransactionTable.module.scss";

interface CashlessTransactionTableProps {
    transactions: CashlessTransaction[];
    currency: string;
    timezone: string;
}

const typeColour: Record<CashlessTransactionType, string> = {
    TOPUP_ONLINE: 'green',
    TOPUP_STAFF: 'teal',
    PURCHASE: 'blue',
    REVERSAL: 'orange',
    REFUND_REMAINING: 'grape',
    CLOSURE: 'gray',
};

export const typeLabel = (type: CashlessTransactionType): string => ({
    TOPUP_ONLINE: t`Online top-up`,
    TOPUP_STAFF: t`Staff top-up`,
    PURCHASE: t`Purchase`,
    REVERSAL: t`Reversal`,
    REFUND_REMAINING: t`Balance refund`,
    CLOSURE: t`Balance closure`,
}[type]);

export const CashlessTransactionTable = ({transactions, currency, timezone}: CashlessTransactionTableProps) => {
    const {eventId} = useParams();
    const reverseMutation = useReverseCashlessTransaction();

    const handleReverse = (transactionId: IdParam) => {
        confirmationDialog(
            t`Reverse this transaction? The amount goes straight back onto the attendee's balance.`,
            () => {
                reverseMutation.mutate({eventId, transactionId}, {
                    onSuccess: () => showSuccess(t`Transaction reversed`),
                    onError: (error: any) => showError(
                        error?.response?.data?.message || t`This transaction could not be reversed`
                    ),
                });
            },
        );
    };

    if (transactions.length === 0) {
        return (
            <NoResultsSplash
                imageHref={"/blank-slate/orders.svg"}
                heading={t`No cashless activity yet`}
                subHeading={<p>{t`Top-ups and purchases will appear here as they happen.`}</p>}
            />
        );
    }

    return (
        <Table>
            <TableHead>
                <MantineTable.Tr>
                    <MantineTable.Th>{t`When`}</MantineTable.Th>
                    <MantineTable.Th>{t`Type`}</MantineTable.Th>
                    <MantineTable.Th>{t`Attendee`}</MantineTable.Th>
                    <MantineTable.Th>{t`Details`}</MantineTable.Th>
                    <MantineTable.Th>{t`Amount`}</MantineTable.Th>
                    <MantineTable.Th>{t`Balance after`}</MantineTable.Th>
                    <MantineTable.Th/>
                </MantineTable.Tr>
            </TableHead>
            <MantineTable.Tbody>
                {transactions.map((transaction) => (
                    <MantineTable.Tr key={transaction.short_id}>
                        <MantineTable.Td>{prettyDate(transaction.created_at, timezone)}</MantineTable.Td>
                        <MantineTable.Td>
                            <Badge variant="light" color={typeColour[transaction.type]}>
                                {typeLabel(transaction.type)}
                            </Badge>
                        </MantineTable.Td>
                        <MantineTable.Td>
                            <Text>{transaction.attendee_name}</Text>
                            <Text size="xs" c="dimmed" className={classes.ticketId}>
                                {transaction.attendee_public_id}
                            </Text>
                        </MantineTable.Td>
                        <MantineTable.Td>
                            {transaction.sales_point_name && (
                                <Text size="sm">{transaction.sales_point_name}</Text>
                            )}
                            {!!transaction.items?.length && (
                                <Text size="xs" c="dimmed">
                                    {transaction.items.map((item) => `${item.quantity} × ${item.product_title}`).join(', ')}
                                </Text>
                            )}
                            {transaction.notes && (
                                <Text size="xs" c="dimmed">{transaction.notes}</Text>
                            )}
                        </MantineTable.Td>
                        <MantineTable.Td>
                            <Text fw={600} c={transaction.amount < 0 ? 'red' : 'green'}>
                                {formatCurrency(transaction.amount, currency)}
                            </Text>
                        </MantineTable.Td>
                        <MantineTable.Td>{formatCurrency(transaction.balance_after, currency)}</MantineTable.Td>
                        <MantineTable.Td>
                            <ActionMenu
                                itemsGroups={[{
                                    label: t`Manage`,
                                    items: [
                                        {
                                            label: t`Reverse`,
                                            icon: <IconArrowBackUp size={14}/>,
                                            color: 'red',
                                            visible: transaction.type !== 'REVERSAL' && transaction.type !== 'CLOSURE' && !!transaction.id,
                                            onClick: () => handleReverse(transaction.id!),
                                            dataTestId: 'cashless-transaction-reverse-menu-item',
                                        },
                                    ],
                                }]}
                            />
                        </MantineTable.Td>
                    </MantineTable.Tr>
                ))}
            </MantineTable.Tbody>
        </Table>
    );
};
