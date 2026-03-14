import {t} from "@lingui/macro";
import {Button, Group, Menu, Text} from "@mantine/core";
import {IconDotsVertical, IconEye, IconSend, IconTrash} from "@tabler/icons-react";
import {useMemo, useState} from "react";
import {CellContext} from "@tanstack/react-table";
import {useDisclosure} from "@mantine/hooks";
import {IdParam, WaitlistEntry, WaitlistEntryStatus} from "../../../types.ts";
import {relativeDate} from "../../../utilites/dates.ts";
import {NoResultsSplash} from "../NoResultsSplash";
import {confirmationDialog} from "../../../utilites/confirmationDialog.tsx";
import {useRemoveWaitlistEntry} from "../../../mutations/useRemoveWaitlistEntry.ts";
import {useOfferSpecificWaitlistEntry} from "../../../mutations/useOfferSpecificWaitlistEntry.ts";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";
import {TanStackTable, TanStackTableColumn} from "../TanStackTable";
import {ManageOrderModal} from "../../modals/ManageOrderModal";
import classes from './WaitlistTable.module.scss';

interface WaitlistTableProps {
    eventId: IdParam;
    entries: WaitlistEntry[];
}

const statusLabelMap: Record<string, () => string> = {
    [WaitlistEntryStatus.Waiting]: () => t`Waiting`,
    [WaitlistEntryStatus.Offered]: () => t`Offered`,
    [WaitlistEntryStatus.Purchased]: () => t`Purchased`,
    [WaitlistEntryStatus.Cancelled]: () => t`Cancelled`,
    [WaitlistEntryStatus.OfferExpired]: () => t`Expired`,
};

const ActionMenu = ({entry, onOffer, onRemove, onViewOrder}: {
    entry: WaitlistEntry;
    onOffer: (entryId: IdParam) => void;
    onRemove: (entryId: number) => void;
    onViewOrder: (orderId: IdParam) => void;
}) => {
    const isWaiting = entry.status === WaitlistEntryStatus.Waiting;
    const isOffered = entry.status === WaitlistEntryStatus.Offered;
    const isExpired = entry.status === WaitlistEntryStatus.OfferExpired;
    const isPurchased = entry.status === WaitlistEntryStatus.Purchased;
    const canOffer = isWaiting || isExpired;
    const canCancel = isWaiting || isOffered;

    return (
        <Group wrap="nowrap" gap={0} justify="flex-end">
            <Menu shadow="md" width={200}>
                <Menu.Target>
                    <div className={classes.actionsMenu}>
                        <Button size="xs" variant="transparent">
                            <IconDotsVertical/>
                        </Button>
                    </div>
                </Menu.Target>
                <Menu.Dropdown>
                    <Menu.Label>{t`Actions`}</Menu.Label>
                    {isPurchased && entry.order_id && (
                        <Menu.Item
                            leftSection={<IconEye size={14}/>}
                            onClick={() => onViewOrder(entry.order_id!)}
                        >
                            {t`View Order`}
                        </Menu.Item>
                    )}
                    {canOffer && (
                        <Menu.Item
                            leftSection={<IconSend size={14}/>}
                            onClick={() => onOffer(entry.id)}
                        >
                            {isExpired ? t`Re-offer Spot` : t`Offer Spot`}
                        </Menu.Item>
                    )}
                    {canCancel && (
                        <Menu.Item
                            color="red"
                            leftSection={<IconTrash size={14}/>}
                            onClick={() => onRemove(entry.id as number)}
                        >
                            {isOffered ? t`Revoke Offer` : t`Remove`}
                        </Menu.Item>
                    )}
                </Menu.Dropdown>
            </Menu>
        </Group>
    );
};

export const WaitlistTable = ({eventId, entries}: WaitlistTableProps) => {
    const removeMutation = useRemoveWaitlistEntry();
    const offerMutation = useOfferSpecificWaitlistEntry();
    const [isOrderModalOpen, orderModal] = useDisclosure(false);
    const [selectedOrderId, setSelectedOrderId] = useState<IdParam>();

    const handleViewOrder = (orderId: IdParam) => {
        setSelectedOrderId(orderId);
        orderModal.open();
    };

    const handleRemove = (entryId: number) => {
        confirmationDialog(
            t`Are you sure you want to remove this entry from the waitlist?`,
            () => {
                removeMutation.mutate({eventId, entryId}, {
                    onSuccess: () => {
                        showSuccess(t`Successfully removed from waitlist`);
                    },
                    onError: () => {
                        showError(t`Failed to remove from waitlist`);
                    },
                });
            },
            {confirm: t`Remove`, cancel: t`Cancel`}
        );
    };

    const handleOffer = (entryId: IdParam) => {
        confirmationDialog(
            t`Are you sure you want to offer a spot to this person? They will receive an email notification.`,
            () => {
                offerMutation.mutate({eventId, entryId}, {
                    onSuccess: () => {
                        showSuccess(t`Successfully offered a spot`);
                    },
                    onError: (error: any) => {
                        const errors = error?.response?.data?.errors;
                        const message = errors
                            ? Object.values(errors).flat().join(', ')
                            : t`Failed to offer spot`;
                        showError(message as string);
                    },
                });
            },
            {confirm: t`Offer`, cancel: t`Cancel`}
        );
    };

    const columns = useMemo<TanStackTableColumn<WaitlistEntry>[]>(
        () => [
            {
                id: 'position',
                header: '#',
                enableHiding: false,
                cell: (info: CellContext<WaitlistEntry, unknown>) => info.row.original.position,
                meta: {
                    headerStyle: {width: 60},
                },
            },
            {
                id: 'contact',
                header: t`Contact`,
                enableHiding: false,
                cell: (info: CellContext<WaitlistEntry, unknown>) => {
                    const entry = info.row.original;
                    return (
                        <div className={classes.contactDetails}>
                            <Text className={classes.contactName}>
                                {entry.first_name} {entry.last_name}
                            </Text>
                            <Text className={classes.contactEmail}>
                                {entry.email}
                            </Text>
                        </div>
                    );
                },
                meta: {
                    headerStyle: {minWidth: 220},
                },
            },
            {
                id: 'product',
                header: t`Product`,
                enableHiding: true,
                cell: (info: CellContext<WaitlistEntry, unknown>) => {
                    const entry = info.row.original;
                    const title = entry.product?.title || '';
                    const label = entry.product_price?.label;
                    return label ? `${title} - ${label}` : title;
                }
            },
            {
                id: 'status',
                header: t`Status`,
                enableHiding: true,
                cell: (info: CellContext<WaitlistEntry, unknown>) => {
                    const entry = info.row.original;
                    return (
                        <div className={classes.statusBadge} data-status={entry.status}>
                            {entry.status ? statusLabelMap[entry.status]() : ''}
                        </div>
                    );
                },
                meta: {
                    headerStyle: {minWidth: 130},
                },
            },
            {
                id: 'joined',
                header: t`Joined`,
                enableHiding: true,
                cell: (info: CellContext<WaitlistEntry, unknown>) => {
                    const entry = info.row.original;
                    return (
                        <Text size="sm" c="dimmed">
                            {entry.created_at ? relativeDate(String(entry.created_at)) : ''}
                        </Text>
                    );
                },
            },
            {
                id: 'actions',
                header: t`Actions`,
                enableHiding: false,
                cell: (info: CellContext<WaitlistEntry, unknown>) => {
                    const entry = info.row.original;
                    return (
                        <div className={classes.actionsMenu}>
                            <ActionMenu
                                entry={entry}
                                onOffer={handleOffer}
                                onRemove={handleRemove}
                                onViewOrder={handleViewOrder}
                            />
                        </div>
                    );
                },
                meta: {
                    sticky: 'right',
                },
            },
        ],
        [eventId]
    );

    if (entries.length === 0) {
        return (
            <NoResultsSplash
                heading={t`No waitlist entries`}
                imageHref={'/blank-slate/waitlist.svg'}
                subHeading={(
                    <p>
                        {t`Entries will appear here when customers join the waitlist for sold out products.`}
                    </p>
                )}
            />
        );
    }

    return (
        <>
            <TanStackTable
                data={entries}
                columns={columns}
            />
            {(selectedOrderId && isOrderModalOpen) && (
                <ManageOrderModal
                    orderId={selectedOrderId}
                    onClose={orderModal.close}
                />
            )}
        </>
    );
};
