import {useState} from "react";
import {t} from "@lingui/macro";
import {useClipboard, useDisclosure} from "@mantine/hooks";
import {
    IconBasketCog,
    IconCopy,
    IconPencil,
    IconReceipt2,
    IconReceiptDollar,
    IconReceiptRefund,
    IconRepeat,
    IconSend,
    IconTrash
} from "@tabler/icons-react";
import {IdParam, Invoice, MessageType, Order} from "../types.ts";
import {EntityAction} from "../components/common/EntityActions";
import {CancelOrderModal} from "../components/modals/CancelOrderModal";
import {RefundOrderModal} from "../components/modals/RefundOrderModal";
import {SendMessageModal} from "../components/modals/SendMessageModal";
import {useResendOrderConfirmation} from "../mutations/useResendOrderConfirmation.ts";
import {useMarkOrderAsPaid} from "../mutations/useMarkOrderAsPaid.ts";
import {orderClient} from "../api/order.client.ts";
import {downloadBinary} from "../utilites/download.ts";
import {withLoadingNotification} from "../utilites/withLoadingNotification.tsx";
import {showError, showSuccess} from "../utilites/notifications.tsx";
import {eventCheckoutUrl} from "../utilites/urlHelper.ts";
import {isOrderRefundable} from "../utilites/orderHelper.ts";

interface UseOrderActionsOptions {
    eventId: IdParam;
    onManage?: (order: Order) => void;
    onEdit?: (order: Order) => void;
}

export const useOrderActions = ({eventId, onManage, onEdit}: UseOrderActionsOptions) => {
    const [isCancelModalOpen, cancelModal] = useDisclosure(false);
    const [isMessageModalOpen, messageModal] = useDisclosure(false);
    const [isRefundModalOpen, refundModal] = useDisclosure(false);
    const [selectedOrderId, setSelectedOrderId] = useState<IdParam>();
    const resendConfirmationMutation = useResendOrderConfirmation();
    const markAsPaidMutation = useMarkOrderAsPaid();
    const clipboard = useClipboard({timeout: 2000});

    const openModal = (order: Order, modal: { open: () => void }) => {
        setSelectedOrderId(order.id);
        modal.open();
    };

    const handleMarkAsPaid = (order: Order) => {
        markAsPaidMutation.mutate({eventId, orderId: order.id}, {
            onSuccess: () => showSuccess(t`Order marked as paid`),
            onError: () => showError(t`There was an error marking the order as paid`)
        });
    };

    const handleResendConfirmation = (order: Order) => {
        resendConfirmationMutation.mutate({eventId, orderId: order.id}, {
            onSuccess: () => showSuccess(t`Your message has been sent`),
            onError: () => showError(t`There was an error sending your message`)
        });
    };

    const handleCopyCustomerLink = (order: Order) => {
        clipboard.copy(eventCheckoutUrl(order.event_id, order.short_id, 'summary'));
        showSuccess(t`Customer link copied to clipboard`);
    };

    const handleInvoiceDownload = async (invoice: Invoice) => {
        await withLoadingNotification(
            async () => {
                const blob = await orderClient.downloadInvoice(eventId, invoice.order_id);
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

    const getOrderActions = (order: Order): EntityAction[] => {
        const isRefundable = isOrderRefundable(order);

        const actions: (EntityAction | false)[] = [
            !!onManage && {
                key: 'manage',
                label: t`Manage order`,
                icon: <IconBasketCog size={14}/>,
                onClick: () => onManage(order),
                group: 'primary',
            },
            !!onEdit && {
                key: 'edit',
                label: t`Edit`,
                icon: <IconPencil size={14}/>,
                onClick: () => onEdit(order),
                group: 'primary',
                dataTestId: 'order-edit-button',
            },
            {
                key: 'message',
                label: t`Message buyer`,
                icon: <IconSend size={14}/>,
                onClick: () => openModal(order, messageModal),
                group: 'primary',
            },
            order.status === 'COMPLETED' && {
                key: 'resend',
                label: t`Resend order email`,
                icon: <IconRepeat size={14}/>,
                onClick: () => handleResendConfirmation(order),
                group: 'primary',
            },
            order.status === 'AWAITING_OFFLINE_PAYMENT' && {
                key: 'mark-as-paid',
                label: t`Mark as paid`,
                icon: <IconReceiptDollar size={14}/>,
                onClick: () => handleMarkAsPaid(order),
                group: 'primary',
            },
            isRefundable && {
                key: 'refund',
                label: t`Refund order`,
                icon: <IconReceiptRefund size={14}/>,
                onClick: () => openModal(order, refundModal),
                group: 'secondary',
            },
            {
                key: 'copy-link',
                label: t`Copy customer link`,
                icon: <IconCopy size={14}/>,
                onClick: () => handleCopyCustomerLink(order),
                group: 'secondary',
            },
            !!order.latest_invoice && {
                key: 'invoice',
                label: t`Download invoice`,
                icon: <IconReceipt2 size={14}/>,
                onClick: () => handleInvoiceDownload(order.latest_invoice as Invoice),
                group: 'secondary',
            },
            order.status !== 'CANCELLED' && {
                key: 'cancel',
                label: t`Cancel order`,
                icon: <IconTrash size={14}/>,
                onClick: () => openModal(order, cancelModal),
                group: 'danger',
                color: 'red',
            },
        ];

        return actions.filter(Boolean) as EntityAction[];
    };

    const orderActionModals = selectedOrderId && (
        <>
            {isRefundModalOpen && <RefundOrderModal onClose={refundModal.close} orderId={selectedOrderId}/>}
            {isCancelModalOpen && <CancelOrderModal onClose={cancelModal.close} orderId={selectedOrderId}/>}
            {isMessageModalOpen && <SendMessageModal
                onClose={messageModal.close}
                orderId={selectedOrderId}
                messageType={MessageType.OrderOwner}
            />}
        </>
    );

    return {
        getOrderActions,
        orderActionModals,
        openMessageModal: (order: Order) => openModal(order, messageModal),
        downloadInvoice: handleInvoiceDownload,
    };
};
