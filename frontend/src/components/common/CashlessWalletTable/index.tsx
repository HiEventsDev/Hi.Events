import {t} from "@lingui/macro";
import {Badge, Table as MantineTable, Text} from "@mantine/core";
import {IconCoin, IconLock, IconLockOpen, IconReceiptRefund} from "@tabler/icons-react";
import {useState} from "react";
import {useDisclosure} from "@mantine/hooks";
import {useParams} from "react-router";
import {CashlessWallet} from "../../../types.ts";
import {NoResultsSplash} from "../NoResultsSplash";
import {Table, TableHead} from "../Table";
import {ActionMenu} from "../ActionMenu";
import {formatCurrency} from "../../../utilites/currency.ts";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";
import {confirmationDialog} from "../../../utilites/confirmationDialog.tsx";
import {useUpdateCashlessWalletStatus} from "../../../mutations/useUpdateCashlessWalletStatus.ts";
import {CashlessTopupModal} from "../../modals/CashlessTopupModal";
import {CashlessRefundModal} from "../../modals/CashlessRefundModal";
import classes from "./CashlessWalletTable.module.scss";

interface CashlessWalletTableProps {
    wallets: CashlessWallet[];
}

const statusColour: Record<CashlessWallet['status'], string> = {
    ACTIVE: 'green',
    FROZEN: 'orange',
    CLOSED: 'gray',
};

export const CashlessWalletTable = ({wallets}: CashlessWalletTableProps) => {
    const {eventId} = useParams();
    const [selectedWallet, setSelectedWallet] = useState<CashlessWallet>();
    const [topupOpen, {open: openTopup, close: closeTopup}] = useDisclosure(false);
    const [refundOpen, {open: openRefund, close: closeRefund}] = useDisclosure(false);
    const statusMutation = useUpdateCashlessWalletStatus();

    const handleStatusToggle = (wallet: CashlessWallet) => {
        const nextStatus = wallet.status === 'FROZEN' ? 'ACTIVE' : 'FROZEN';
        const message = nextStatus === 'FROZEN'
            ? t`Freezing this balance blocks any further spending. Continue?`
            : t`Unfreeze this balance so it can be spent again?`;

        confirmationDialog(message, () => {
            statusMutation.mutate({eventId, walletId: wallet.id, status: nextStatus}, {
                onSuccess: () => showSuccess(t`Balance updated`),
                onError: (error: any) => showError(error?.response?.data?.message || t`Something went wrong. Please try again.`),
            });
        });
    };

    if (wallets.length === 0) {
        return (
            <NoResultsSplash
                imageHref={"/blank-slate/tickets.svg"}
                heading={t`No cashless balances yet`}
                subHeading={(
                    <p>
                        {t`A balance is created the first time an attendee tops up, or the first time your staff loads their ticket at a sales point.`}
                    </p>
                )}
            />
        );
    }

    return (
        <>
            <Table>
                <TableHead>
                    <MantineTable.Tr>
                        <MantineTable.Th>{t`Attendee`}</MantineTable.Th>
                        <MantineTable.Th>{t`Ticket ID`}</MantineTable.Th>
                        <MantineTable.Th>{t`Balance`}</MantineTable.Th>
                        <MantineTable.Th>{t`Topped up`}</MantineTable.Th>
                        <MantineTable.Th>{t`Spent`}</MantineTable.Th>
                        <MantineTable.Th>{t`Status`}</MantineTable.Th>
                        <MantineTable.Th/>
                    </MantineTable.Tr>
                </TableHead>
                <MantineTable.Tbody>
                    {wallets.map((wallet) => (
                        <MantineTable.Tr key={wallet.id}>
                            <MantineTable.Td>
                                <Text fw={500}>{wallet.attendee_first_name} {wallet.attendee_last_name}</Text>
                                <Text size="xs" c="dimmed">{wallet.attendee_email}</Text>
                            </MantineTable.Td>
                            <MantineTable.Td>
                                <Text className={classes.ticketId}>{wallet.attendee_public_id}</Text>
                            </MantineTable.Td>
                            <MantineTable.Td>
                                <Text fw={600}>{formatCurrency(wallet.balance, wallet.currency)}</Text>
                            </MantineTable.Td>
                            <MantineTable.Td>{formatCurrency(wallet.total_topped_up, wallet.currency)}</MantineTable.Td>
                            <MantineTable.Td>{formatCurrency(wallet.total_spent, wallet.currency)}</MantineTable.Td>
                            <MantineTable.Td>
                                <Badge variant="light" color={statusColour[wallet.status]}>
                                    {wallet.status}
                                </Badge>
                            </MantineTable.Td>
                            <MantineTable.Td>
                                {wallet.status !== 'CLOSED' && <ActionMenu
                                    itemsGroups={[{
                                        label: t`Manage`,
                                        items: [
                                            {
                                                label: t`Top up`,
                                                icon: <IconCoin size={14}/>,
                                                onClick: () => {
                                                    setSelectedWallet(wallet);
                                                    openTopup();
                                                },
                                                dataTestId: 'cashless-wallet-topup-menu-item',
                                            },
                                            {
                                                label: t`Refund balance`,
                                                icon: <IconReceiptRefund size={14}/>,
                                                onClick: () => {
                                                    setSelectedWallet(wallet);
                                                    openRefund();
                                                },
                                                dataTestId: 'cashless-wallet-refund-menu-item',
                                            },
                                            {
                                                label: wallet.status === 'FROZEN' ? t`Unfreeze` : t`Freeze`,
                                                icon: wallet.status === 'FROZEN' ? <IconLockOpen size={14}/> :
                                                    <IconLock size={14}/>,
                                                onClick: () => handleStatusToggle(wallet),
                                            },
                                        ],
                                    }]}
                                />}
                            </MantineTable.Td>
                        </MantineTable.Tr>
                    ))}
                </MantineTable.Tbody>
            </Table>

            {topupOpen && selectedWallet && (
                <CashlessTopupModal wallet={selectedWallet} onClose={closeTopup}/>
            )}
            {refundOpen && selectedWallet && (
                <CashlessRefundModal wallet={selectedWallet} onClose={closeRefund}/>
            )}
        </>
    );
};
