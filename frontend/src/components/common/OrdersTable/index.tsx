import {t} from "@lingui/macro";
import {Anchor, Button, Group, Menu, Popover, Text, Tooltip} from '@mantine/core';
import {Event, IdParam, Invoice, MessageType, Order} from "../../../types.ts";
import {
    IconAlertCircle,
    IconBasketCog,
    IconCash,
    IconCheck,
    IconClock,
    IconClockPause,
    IconCopy,
    IconCreditCard,
    IconDotsVertical,
    IconFileInvoice,
    IconFileOff,
    IconHelp,
    IconReceipt2,
    IconReceiptDollar,
    IconReceiptRefund,
    IconRepeat,
    IconSend,
    IconTicket,
    IconTrash,
    IconX
} from "@tabler/icons-react";
import {relativeDate} from "../../../utilites/dates.ts";
import {ManageOrderModal} from "../../modals/ManageOrderModal";
import {useClipboard, useDisclosure} from "@mantine/hooks";
import {useMemo, useState} from "react";
import {CancelOrderModal} from "../../modals/CancelOrderModal";
import {SendMessageModal} from "../../modals/SendMessageModal";
import {NoResultsSplash} from "../NoResultsSplash";
import {RefundOrderModal} from "../../modals/RefundOrderModal";
import classes from "./OrdersTable.module.scss";
import {useResendOrderConfirmation} from "../../../mutations/useResendOrderConfirmation.ts";
import {formatNumber} from "../../../utilites/helpers.ts";
import {useUrlHash} from "../../../hooks/useUrlHash.ts";
import {useMarkOrderAsPaid} from "../../../mutations/useMarkOrderAsPaid.ts";
import {orderClient} from "../../../api/order.client.ts";
import {downloadBinary} from "../../../utilites/download.ts";
import {withLoadingNotification} from "../../../utilites/withLoadingNotification.tsx";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";
import {TanStackTable, TanStackTableColumn} from "../TanStackTable";
import {ColumnVisibilityToggle} from "../ColumnVisibilityToggle";
import {CellContext} from "@tanstack/react-table";
import {formatCurrency} from "../../../utilites/currency.ts";

interface OrdersTableProps {
    event: Event,
    orders: Order[];
}

export const OrdersTable = ({orders, event}: OrdersTableProps) => {
    const [isViewModalOpen, viewModal] = useDisclosure(false);
    const [isCancelModalOpen, cancelModal] = useDisclosure(false);
    const [isMessageModalOpen, messageModal] = useDisclosure(false);
    const [isRefundModalOpen, refundModal] = useDisclosure(false);
    const [orderId, setOrderId] = useState<IdParam>();
    const [emailPopoverId, setEmailPopoverId] = useState<IdParam | null>(null);
    const resendConfirmationMutation = useResendOrderConfirmation();
    const markAsPaidMutation = useMarkOrderAsPaid();
    const clipboard = useClipboard({timeout: 2000});

    useUrlHash(/^#order-(\d+)$/, (matches => {
        const orderId = matches![1];
        setOrderId(orderId);
        viewModal.open();
    }));

    const handleModalClick = (orderId: IdParam, modal: { open: () => void }) => {
        setOrderId(orderId);
        modal.open();
    }

    const handleMarkAsPaid = (eventId: IdParam, orderId: IdParam) => {
        markAsPaidMutation.mutate({eventId, orderId}, {
            onSuccess: () => showSuccess(t`Order marked as paid`),
            onError: () => showError(t`There was an error marking the order as paid`)
        });
    }

    const handleResendConfirmation = (eventId: IdParam, orderId: IdParam) => {
        resendConfirmationMutation.mutate({eventId, orderId}, {
            onSuccess: () => showSuccess(t`Your message has been sent`),
            onError: () => showError(t`There was an error sending your message`)
        });
    }

    const handleInvoiceDownload = async (invoice: Invoice) => {
        await withLoadingNotification(
            async () => {
                const blob = await orderClient.downloadInvoice(event.id, invoice.order_id);
                downloadBinary(blob, invoice.invoice_number + '.pdf');
            },
            {
                loading: {
                    title: t`Downloading Invoice`,
                    message: t`Please wait while we prepare your invoice...`
                },
                success: {
                    title: t`Success`,
                    message: t`Invoice downloaded successfully`
                },
                error: {
                    title: t`Error`,
                    message: t`Failed to download invoice. Please try again.`
                }
            }
        );
    };

    const handleCopyEmail = (email: string) => {
        clipboard.copy(email);
        showSuccess(t`Email address copied to clipboard`);
        setEmailPopoverId(null);
    };

    const handleMessageFromEmail = (order: Order) => {
        setEmailPopoverId(null);
        handleModalClick(order.id, messageModal);
    };

    const formatTime = (dateString: string): string => {
        const date = new Date(dateString);
        return date.toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true,
        });
    };

    const ActionMenu = ({order}: { order: Order }) => {
        const isRefundable = !order.is_free_order
            && order.status !== 'AWAITING_OFFLINE_PAYMENT'
            && order.payment_provider === 'STRIPE'
            && order.refund_status !== 'REFUNDED';

        return (
            <Group wrap={'nowrap'} gap={0} justify={'flex-end'}>
                <Menu shadow="md" width={200}>
                    <Menu.Target>
                        <div className={classes.action}>
                            <Button size={"xs"} variant={"transparent"}>
                                <IconDotsVertical/>
                            </Button>
                        </div>
                    </Menu.Target>

                    <Menu.Dropdown>
                        <Menu.Label>{t`Manage`}</Menu.Label>
                        <Menu.Item onClick={() => handleModalClick(order.id, viewModal)}
                                   leftSection={<IconBasketCog size={14}/>}>{t`Manage order`}</Menu.Item>
                        <Menu.Item onClick={() => handleModalClick(order.id, messageModal)}
                                   leftSection={<IconSend size={14}/>}>{t`Message buyer`}</Menu.Item>

                        {order.latest_invoice && (
                            <Menu.Item onClick={() => handleInvoiceDownload(order.latest_invoice as Invoice)}
                                       leftSection={<IconReceipt2 size={14}/>}>{t`Download invoice`}</Menu.Item>
                        )}

                        {order.status === 'AWAITING_OFFLINE_PAYMENT' && (
                            <Menu.Item onClick={() => handleMarkAsPaid(event.id, order.id)}
                                       leftSection={<IconReceiptDollar size={14}/>}>{t`Mark as paid`}</Menu.Item>
                        )}

                        {isRefundable && (
                            <Menu.Item onClick={() => handleModalClick(order.id, refundModal)}
                                       leftSection={<IconReceiptRefund size={14}/>}>{t`Refund order`}</Menu.Item>
                        )}

                        {order.status === 'COMPLETED' && (
                            <Menu.Item
                                onClick={() => handleResendConfirmation(event.id, order.id)}
                                leftSection={<IconRepeat size={14}/>}>
                                {t`Resend order email`}
                            </Menu.Item>
                        )}

                        {order.status !== 'CANCELLED' && (
                            <>
                                <Menu.Divider/>
                                <Menu.Label>{t`Danger zone`}</Menu.Label>
                                <Menu.Item color="red"
                                           onClick={() => handleModalClick(order.id, cancelModal)}
                                           leftSection={<IconTrash size={14}/>}>
                                    {t`Cancel order`}
                                </Menu.Item>
                            </>
                        )}
                    </Menu.Dropdown>
                </Menu>
            </Group>
        );
    }

    const columns = useMemo<TanStackTableColumn<Order>[]>(
        () => [
            {
                id: 'customer',
                header: t`Customer`,
                enableHiding: false,
                cell: (info: CellContext<Order, unknown>) => {
                    const order = info.row.original;
                    return (
                        <div className={classes.customerDetails}>
                            <div className={classes.nameRow}>
                                <Anchor
                                    onClick={() => handleModalClick(order.id, viewModal)}
                                    className={classes.customerName}
                                    style={{cursor: 'pointer'}}
                                >
                                    {order.first_name} {order.last_name}
                                </Anchor>
                                {order.company_name && (
                                    <Text className={classes.companyName}>
                                        {order.company_name}
                                    </Text>
                                )}
                            </div>
                            <Popover
                                opened={emailPopoverId === order.id}
                                onChange={(opened) => {
                                    if (!opened) setEmailPopoverId(null);
                                }}
                                width={200}
                                position="bottom"
                                withArrow
                                shadow="md"
                            >
                                <Popover.Target>
                                    <Anchor
                                        onClick={() => setEmailPopoverId(order.id)}
                                        className={classes.customerEmail}
                                        style={{cursor: 'pointer'}}
                                    >
                                        {order.email}
                                    </Anchor>
                                </Popover.Target>
                                <Popover.Dropdown>
                                    <Group gap="xs" style={{flexDirection: 'column', width: '100%'}}>
                                        <Button
                                            fullWidth
                                            variant="light"
                                            leftSection={<IconSend size={16}/>}
                                            onClick={() => handleMessageFromEmail(order)}
                                        >
                                            {t`Message`}
                                        </Button>
                                        <Button
                                            fullWidth
                                            variant="light"
                                            color="gray"
                                            leftSection={<IconCopy size={16}/>}
                                            onClick={() => handleCopyEmail(order.email)}
                                        >
                                            {t`Copy Email`}
                                        </Button>
                                    </Group>
                                </Popover.Dropdown>
                            </Popover>
                        </div>
                    );
                },
                meta: {
                    headerStyle: {minWidth: 280},
                },
            },
            {
                id: 'orderDetails',
                header: t`Order Details`,
                enableHiding: true,
                cell: (info: CellContext<Order, unknown>) => {
                    const order = info.row.original;
                    return (
                        <div className={classes.orderDetails}>
                            <Anchor
                                onClick={() => handleModalClick(order.id, viewModal)}
                                className={classes.orderId}
                                style={{cursor: 'pointer'}}
                            >
                                {order.public_id}
                            </Anchor>
                            <div className={classes.orderMeta}>
                                <Text className={classes.createdDate}>
                                    {relativeDate(order.created_at)}
                                </Text>
                                {order.latest_invoice ? (
                                    <Anchor
                                        onClick={() => handleInvoiceDownload(order.latest_invoice as Invoice)}
                                        className={classes.invoiceLink}
                                        style={{cursor: 'pointer'}}
                                    >
                                        <IconFileInvoice size={14}/>
                                        {t`Invoice`} #{order.latest_invoice.invoice_number}
                                    </Anchor>
                                ) : (
                                    <Text className={classes.noInvoice}>
                                        <IconFileOff size={14}/>
                                        {t`No invoice`}
                                    </Text>
                                )}
                                {order.status === 'RESERVED' && order.reserved_until && (
                                    <Text className={classes.reservedUntil}>
                                        <IconClock size={14}/>
                                        {t`Reserved until`} {formatTime(order.reserved_until)}
                                    </Text>
                                )}
                            </div>
                        </div>
                    );
                },
            },
            {
                id: 'items',
                header: t`Items`,
                enableHiding: true,
                cell: (info: CellContext<Order, unknown>) => {
                    const order = info.row.original;
                    const totalQuantity = order.order_items?.reduce((sum, item) => sum + item.quantity, 0) || 0;
                    const itemBreakdown = order.order_items?.map(item =>
                        `${item.quantity}x ${item.item_name}`
                    ).join('\n') || '';

                    return (
                        <Tooltip
                            label={itemBreakdown}
                            multiline
                            withArrow
                            disabled={!itemBreakdown}
                        >
                            <div className={classes.itemsBadge}>
                                <IconTicket size={14}/>
                                {formatNumber(totalQuantity)} {t`item(s)`}
                            </div>
                        </Tooltip>
                    );
                },
            },
            {
                id: 'amount',
                header: t`Amount`,
                enableHiding: true,
                cell: (info: CellContext<Order, unknown>) => {
                    const order = info.row.original;
                    return (
                        <div className={classes.amountDetails}>
                            <Text className={classes.amountGross}>
                                {formatCurrency(order.total_gross, order.currency)}
                            </Text>
                            <Text className={classes.amountBreakdown}>
                                {t`Tax`}: {formatCurrency(order.total_tax, order.currency)} •
                                {' '}{t`Fees`}: {formatCurrency(order.total_fee, order.currency)}
                            </Text>
                            {order.refund_status && (
                                <Text className={classes.refundedAmount} data-refund-status={order.refund_status}>
                                    {order.refund_status === 'REFUNDED' && t`Refunded: ${formatCurrency(order.total_refunded, order.currency)}`}
                                    {order.refund_status === 'PARTIALLY_REFUNDED' && t`Partially refunded: ${formatCurrency(order.total_refunded, order.currency)}`}
                                    {order.refund_status === 'REFUND_PENDING' && t`Refund pending`}
                                    {order.refund_status === 'REFUND_FAILED' && t`Refund failed`}
                                </Text>
                            )}
                        </div>
                    );
                },
            },
            {
                id: 'payment',
                header: t`Payment`,
                enableHiding: true,
                cell: (info: CellContext<Order, unknown>) => {
                    const order = info.row.original;
                    return (
                        <div className={classes.paymentStatus}>
                            {order.payment_provider === 'STRIPE' ? (
                                <>
                                    <IconCreditCard size={16}/>
                                    <Text>{t`Stripe`}</Text>
                                </>
                            ) : order.payment_provider === 'OFFLINE' ? (
                                <>
                                    <IconCash size={16}/>
                                    <Text>{t`Offline`}</Text>
                                </>
                            ) : (
                                <>
                                    <IconHelp size={16}/>
                                    <Text>{t`Other`}</Text>
                                </>
                            )}
                        </div>
                    );
                },
            },
            {
                id: 'status',
                header: t`Status`,
                enableHiding: true,
                cell: (info: CellContext<Order, unknown>) => {
                    const order = info.row.original;
                    return (
                        <div className={classes.statusBadge} data-status={order.status}>
                            {order.status === 'COMPLETED' && (
                                <>
                                    <IconCheck size={14}/>
                                    {t`Completed`}
                                </>
                            )}
                            {order.status === 'RESERVED' && (
                                <>
                                    <IconClock size={14}/>
                                    {t`Reserved`}
                                </>
                            )}
                            {order.status === 'AWAITING_OFFLINE_PAYMENT' && (
                                <>
                                    <IconClockPause size={14}/>
                                    {t`Awaiting Payment`}
                                </>
                            )}
                            {order.status === 'CANCELLED' && (
                                <>
                                    <IconX size={14}/>
                                    {t`Cancelled`}
                                </>
                            )}
                            {order.status === 'ABANDONED' && (
                                <>
                                    <IconAlertCircle size={14}/>
                                    {t`Abandoned`}
                                </>
                            )}
                        </div>
                    );
                },
            },
            {
                id: 'actions',
                header: t`Actions`,
                enableHiding: false,
                cell: (info: CellContext<Order, unknown>) => {
                    const order = info.row.original;
                    return (
                        <div className={classes.actionsMenu}>
                            <ActionMenu order={order}/>
                        </div>
                    );
                },
                meta: {
                    sticky: 'right',
                },
            },
        ],
        [event.id, emailPopoverId]
    );

    if (orders.length === 0) {
        return <NoResultsSplash
            imageHref={'/blank-slate/orders.svg'}
            heading={t`No orders to show`}
            subHeading={(
                <p>
                    {t`Your orders will appear here once they start rolling in.`}
                </p>
            )}
        />
    }

    return (
        <>
            <TanStackTable
                data={orders}
                columns={columns}
                storageKey="orders-table"
                enableColumnVisibility={true}
                renderColumnVisibilityToggle={(table) => <ColumnVisibilityToggle table={table}/>}
            />
            {orderId && (
                <>
                    {isRefundModalOpen && <RefundOrderModal onClose={refundModal.close} orderId={orderId}/>}
                    {isViewModalOpen && <ManageOrderModal onClose={viewModal.close} orderId={orderId}/>}
                    {isCancelModalOpen && <CancelOrderModal onClose={cancelModal.close} orderId={orderId}/>}
                    {isMessageModalOpen && <SendMessageModal
                        onClose={messageModal.close}
                        orderId={orderId}
                        messageType={MessageType.OrderOwner}
                    />}
                </>
            )}
        </>
    )
};
