import {t} from "@lingui/macro";
import {Anchor, Badge, Button, Group, Menu, Table as MantineTable, Tooltip} from '@mantine/core';
import {Event, IdParam, MessageType, Order} from "../../../types.ts";
import {
    IconCheck,
    IconDotsVertical,
    IconEye,
    IconInfoCircle,
    IconReceiptRefund,
    IconRepeat,
    IconSend,
    IconTrash
} from "@tabler/icons-react";
import {prettyDate, relativeDate} from "../../../utilites/dates.ts";
import {ViewOrderModal} from "../../modals/ViewOrderModal";
import {useDisclosure} from "@mantine/hooks";
import {useState} from "react";
import {CancelOrderModal} from "../../modals/CancelOrderModal";
import {SendMessageModal} from "../../modals/SendMessageModal";
import {notifications} from "@mantine/notifications";
import {NoResultsSplash} from "../NoResultsSplash";
import {OrderAmountPopover} from "../OrderAmountPopover";
import {RefundOrderModal} from "../../modals/RefundOrderModal";
import classes from "./OrdersTable.module.scss";
import {Card} from "../Card";
import {Table, TableHead} from "../Table";
import {ShowForDesktop, ShowForMobile} from "../Responsive/ShowHideComponents.tsx";
import {useResendOrderConfirmation} from "../../../mutations/useResendOrderConfirmation.ts";
import {OrderStatusBadge} from "../OrderStatusBadge";
import {formatNumber} from "../../../utilites/helpers.ts";
import {useUrlHash} from "../../../hooks/useUrlHash.ts";

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
    const resendConfirmationMutation = useResendOrderConfirmation();

    useUrlHash(/^#order-(\d+)$/, (matches => {
        const orderId = matches![1];
        setOrderId(orderId);
        viewModal.open();
    }));

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

    const handleModalClick = (orderId: IdParam, modal: { open: () => void }) => {
        setOrderId(orderId);
        modal.open();
    }

    const handleResendConfirmation = (eventId: IdParam, orderId: IdParam) => {
        resendConfirmationMutation.mutate({eventId, orderId}, {
            onSuccess: () => {
                notifications.show({
                    message: t`Your message has been sent`,
                    icon: <IconCheck/>
                })
            },
            onError: () => {
                notifications.show({
                    message: t`There was an error sending your message`,
                    icon: <IconCheck/>
                })
            }
        });
    }

    const ActionMenu = ({order}: { order: Order }) => {
        return <Group wrap={'nowrap'} gap={0} justify={'flex-end'}>
            <Menu shadow="md" width={200}>
                <Menu.Target>
                    <div className={classes.action}>
                        <div className={classes.mobileAction}>
                            <ShowForMobile>
                                <Button size={"xs"} variant={"light"}>
                                    {t`Manage`}
                                </Button>
                            </ShowForMobile>
                        </div>
                        <div className={classes.desktopAction}>
                            <ShowForDesktop>
                                <Button size={"xs"} variant={"transparent"}>
                                    <IconDotsVertical/>
                                </Button>
                            </ShowForDesktop>
                        </div>
                    </div>
                </Menu.Target>

                <Menu.Dropdown>
                    <Menu.Label>{t`Manage`}</Menu.Label>
                    <Menu.Item onClick={() => handleModalClick(order.id, viewModal)}
                               leftSection={<IconEye size={14}/>}>{t`View order`}</Menu.Item>
                    <Menu.Item onClick={() => handleModalClick(order.id, messageModal)}
                               leftSection={<IconSend size={14}/>}>{t`Message buyer`}</Menu.Item>

                    {!order.is_free_order && (
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
        </Group>;
    }

    const OrderTableDesktop = () => (
        <ShowForDesktop>
            <Table>
                <TableHead>
                    <MantineTable.Tr>
                        <MantineTable.Th>{t`Order #`}</MantineTable.Th>
                        <MantineTable.Th>{t`Customer`}</MantineTable.Th>
                        <MantineTable.Th>{t`Attendees`}</MantineTable.Th>
                        <MantineTable.Th>{t`Amount`}</MantineTable.Th>
                        <MantineTable.Th>{t`Created`}</MantineTable.Th>
                        <MantineTable.Th>{t`Status`}</MantineTable.Th>
                        <MantineTable.Th></MantineTable.Th>
                    </MantineTable.Tr>
                </TableHead>
                <MantineTable.Tbody>
                    {orders.map((order) => {
                        return (
                            <MantineTable.Tr key={order.id}>
                                <MantineTable.Td>
                                    <Anchor onClick={() => handleModalClick(order.id, viewModal)}>
                                        <Badge variant={'outline'}>{order.public_id}</Badge>
                                    </Anchor>
                                </MantineTable.Td>
                                <MantineTable.Td>
                                    <div>
                                        <b>{order.first_name + ' ' + order.last_name}</b>
                                    </div>
                                    <Anchor target={'_blank'} href={`mailto:${order.email}`}>{order.email}</Anchor>
                                </MantineTable.Td>
                                <MantineTable.Td>
                                    <Anchor onClick={() => handleModalClick(order.id, viewModal)}>
                                        {formatNumber(order.attendees?.length as number)}
                                    </Anchor>
                                </MantineTable.Td>
                                <MantineTable.Td>
                                    <OrderAmountPopover event={event} order={order}/>
                                </MantineTable.Td>
                                <MantineTable.Td>
                                    <Tooltip.Floating label={prettyDate(order.created_at, event.timezone)}>
                                        <span>
                                            {relativeDate(order.created_at)}
                                        </span>
                                    </Tooltip.Floating>
                                </MantineTable.Td>
                                <MantineTable.Td>
                                    <OrderStatusBadge order={order}/>
                                </MantineTable.Td>
                                <MantineTable.Td>
                                    <ActionMenu order={order}/>
                                </MantineTable.Td>
                            </MantineTable.Tr>
                        );
                    })}
                </MantineTable.Tbody>
            </Table>
        </ShowForDesktop>
    );

    const OrderTableMobile = () => (
        <ShowForMobile>
            {orders.map((order) => {
                return (
                    <Card className={classes.orderCard} key={order.id}>
                        <div className={classes.colDetails}>

                            <div className={classes.amount}>
                                <OrderAmountPopover event={event} order={order}/>
                            </div>
                            <div className={classes.name}>
                                {order.first_name + ' ' + order.last_name}
                            </div>
                            <Anchor className={classes.email} target={'_blank'}
                                    href={`mailto:${order.email}`}>{order.email}</Anchor>

                            <span className={classes.reference}>
                                {t`Reference`}: <b>{order.public_id}</b>
                                <Anchor onClick={() => handleModalClick(order.id, viewModal)}>
                                    <IconInfoCircle size={13}/>
                                </Anchor>
                            </span>
                            <Tooltip.Floating label={prettyDate(order.created_at, event.timezone)}>
                                <span className={classes.createdDate}>
                                    {t`Created`}: <b>{relativeDate(order.created_at)}</b>
                                </span>
                            </Tooltip.Floating>

                        </div>
                        <div className={classes.colActions}>
                            <div className={classes.status}>
                                <OrderStatusBadge order={order}/>
                            </div>
                            <div className={classes.actionButton}>
                                <ActionMenu order={order}/>
                            </div>
                        </div>
                    </Card>
                );
            })}
        </ShowForMobile>
    );

    return (
        <>
            <OrderTableDesktop/>
            <OrderTableMobile/>
            {orderId && (
                <>
                    {isRefundModalOpen && <RefundOrderModal onClose={refundModal.close} orderId={orderId}/>}
                    {isViewModalOpen && <ViewOrderModal onClose={viewModal.close} orderId={orderId}/>}
                    {isCancelModalOpen && <CancelOrderModal onClose={cancelModal.close} orderId={orderId}/>}
                    {isMessageModalOpen && <SendMessageModal
                        onClose={messageModal.close}
                        orderId={orderId}
                        messageType={MessageType.Order}
                    />}
                </>
            )}
        </>
    )
};
