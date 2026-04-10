import {Anchor, Button, Progress} from '@mantine/core';
import {CheckInList, Event, EventType, IdParam} from "../../../types.ts";
import {
    IconCalendarEvent,
    IconCheck,
    IconCopy,
    IconExternalLink,
    IconPencil,
    IconPlus,
    IconTrash,
    IconUsers,
    IconX,
} from "@tabler/icons-react";
import {useMemo, useState} from "react";
import {useDisclosure} from "@mantine/hooks";
import {useParams} from "react-router";
import {t, Trans} from "@lingui/macro";
import {NoResultsSplash} from "../NoResultsSplash";
import {EditCheckInListModal} from "../../modals/EditCheckInListModal";
import {useDeleteCheckInList} from "../../../mutations/useDeleteCheckInList";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";
import {confirmationDialog} from "../../../utilites/confirmationDialog.tsx";
import {TanStackTable, TanStackTableColumn} from "../TanStackTable";
import {ActionMenu} from '../ActionMenu';
import {CellContext} from "@tanstack/react-table";
import Truncate from "../Truncate";
import {formatDateWithLocale} from "../../../utilites/dates.ts";
import classes from './CheckInListTable.module.scss';

interface CheckInListTableProps {
    checkInLists: CheckInList[];
    openCreateModal: () => void;
    event?: Event;
}

export const CheckInListTable = ({checkInLists, openCreateModal, event}: CheckInListTableProps) => {
    const [editModalOpen, {open: openEditModal, close: closeEditModal}] = useDisclosure(false);
    const [selectedCheckInListId, setSelectedCheckInListId] = useState<IdParam>();
    const deleteMutation = useDeleteCheckInList();
    const {eventId} = useParams();
    const isRecurring = event?.type === EventType.RECURRING;

    const handleDeleteCheckInList = (checkInListId: IdParam, eventId: IdParam) => {
        deleteMutation.mutate({checkInListId, eventId}, {
            onSuccess: () => {
                showSuccess(t`Check-In List deleted successfully`);
            },
            onError: (error: any) => {
                showError(error.message);
            }
        });
    }

    const columns = useMemo<TanStackTableColumn<CheckInList>[]>(
        () => {
            const allColumns: TanStackTableColumn<CheckInList>[] = [
                {
                    id: 'name',
                    header: t`Check-In List`,
                    enableHiding: false,
                    cell: (info: CellContext<CheckInList, unknown>) => {
                        const list = info.row.original;
                        return (
                            <div className={classes.listDetails}>
                                <Anchor
                                    className={classes.listName}
                                    onClick={() => {
                                        setSelectedCheckInListId(list.id as IdParam);
                                        openEditModal();
                                    }}
                                >
                                    <Truncate text={list.name} length={40}/>
                                </Anchor>
                                {list.products && list.products.length > 0 && (
                                    <div className={classes.productsText}>
                                        {list.products.length === 1
                                            ? t`Includes 1 product`
                                            : <Trans>Includes {list.products.length} products</Trans>
                                        }
                                    </div>
                                )}
                            </div>
                        );
                    },
                    meta: {
                        headerStyle: {minWidth: 250},
                    },
                },
                {
                    id: 'occurrence',
                    header: t`Date`,
                    enableHiding: true,
                    cell: (info: CellContext<CheckInList, unknown>) => {
                        const list = info.row.original;
                        const occurrence = list.event_occurrence;

                        if (!occurrence || !event?.timezone) {
                            return (
                                <div className={classes.occurrenceText}>
                                    {t`All Dates`}
                                </div>
                            );
                        }

                        return (
                            <div className={classes.occurrenceContainer}>
                                <span className={classes.occurrenceChip}>
                                    <IconCalendarEvent size={12}/>
                                    {formatDateWithLocale(occurrence.start_date, 'shortDate', event.timezone)}
                                    {' '}
                                    {formatDateWithLocale(occurrence.start_date, 'timeOnly', event.timezone)}
                                    {occurrence.label && ` · ${occurrence.label}`}
                                </span>
                            </div>
                        );
                    },
                    meta: {
                        headerStyle: {minWidth: 160},
                    },
                },
                {
                    id: 'progress',
                    header: t`Check-Ins`,
                    enableHiding: true,
                    cell: (info: CellContext<CheckInList, unknown>) => {
                        const list = info.row.original;
                        const percentage = list.total_attendees === 0
                            ? 0
                            : (list.checked_in_attendees / list.total_attendees) * 100;
                        return (
                            <div className={classes.progressContainer}>
                                <Progress
                                    value={percentage}
                                    radius="xl"
                                    color={list.checked_in_attendees === list.total_attendees && list.total_attendees > 0 ? 'primary' : 'green'}
                                    size="md"
                                />
                                <div className={classes.progressText}>
                                    <IconUsers size={14}/>
                                    {list.checked_in_attendees} / {list.total_attendees}
                                </div>
                            </div>
                        );
                    },
                    meta: {
                        headerStyle: {minWidth: 160},
                    },
                },
                {
                    id: 'status',
                    header: t`Status`,
                    enableHiding: true,
                    cell: (info: CellContext<CheckInList, unknown>) => {
                        const list = info.row.original;
                        const isActive = !list.is_expired && list.is_active;
                        return (
                            <div className={classes.statusBadge} data-status={isActive ? 'active' : 'inactive'}>
                                {isActive ? (
                                    <>
                                        <IconCheck size={14}/>
                                        {t`Active`}
                                    </>
                                ) : (
                                    <>
                                        <IconX size={14}/>
                                        {t`Inactive`}
                                    </>
                                )}
                            </div>
                        );
                    },
                    meta: {
                        headerStyle: {minWidth: 100},
                    },
                },
                {
                    id: 'actions',
                    header: '',
                    enableHiding: false,
                    cell: (info: CellContext<CheckInList, unknown>) => {
                        const list = info.row.original;
                        return (
                            <div className={classes.actionsMenu}>
                                <ActionMenu itemsGroups={[
                                    {
                                        label: t`Manage`,
                                        items: [
                                            {
                                                label: t`Edit Check-In List`,
                                                icon: <IconPencil size={14}/>,
                                                onClick: () => {
                                                    setSelectedCheckInListId(list.id as IdParam);
                                                    openEditModal();
                                                }
                                            },
                                            {
                                                label: t`Copy Check-In URL`,
                                                icon: <IconCopy size={14}/>,
                                                onClick: () => {
                                                    navigator.clipboard.writeText(
                                                        `${window.location.origin}/check-in/${list.short_id}`
                                                    ).then(() => {
                                                        showSuccess(t`Check-In URL copied to clipboard`);
                                                    });
                                                }
                                            },
                                            {
                                                label: t`Open Check-In Page`,
                                                icon: <IconExternalLink size={14}/>,
                                                onClick: () => {
                                                    window.open(`/check-in/${list.short_id}`, '_blank');
                                                }
                                            }
                                        ],
                                    },
                                    {
                                        label: t`Danger zone`,
                                        items: [
                                            {
                                                label: t`Delete Check-In List`,
                                                icon: <IconTrash size={14}/>,
                                                onClick: () => {
                                                    confirmationDialog(
                                                        t`Are you sure you would like to delete this Check-In List?`,
                                                        () => {
                                                            handleDeleteCheckInList(
                                                                list.id as IdParam,
                                                                eventId,
                                                            );
                                                        })
                                                },
                                                color: 'red',
                                            },
                                        ],
                                    },
                                ]}/>
                            </div>
                        );
                    },
                    meta: {
                        sticky: 'right',
                    },
                },
            ];

            return allColumns.filter(column => {
                if (column.id === 'occurrence' && !isRecurring) {
                    return false;
                }
                return true;
            });
        },
        [eventId, isRecurring, event?.timezone]
    );

    if (checkInLists.length === 0) {
        return (
            <NoResultsSplash
                heading={t`No Check-In Lists`}
                imageHref={'/blank-slate/check-in-lists.svg'}
                subHeading={(
                    <>
                        <p>
                            <Trans>
                                <p>
                                    Check-in lists help you manage event entry by day, area, or ticket type. You can link tickets to specific lists such as VIP zones or Day 1 passes and share a secure check-in link with staff. No account is required. Check-in works on mobile, desktop, or tablet, using a device camera or HID USB scanner.                                </p>
                            </Trans>
                        </p>
                        <Button
                            size={'xs'}
                            leftSection={<IconPlus/>}
                            color={'green'}
                            onClick={() => openCreateModal()}>{t`Create Check-In List`}
                        </Button>
                    </>
                )}
            />
        );
    }

    return (
        <>
            <TanStackTable
                data={checkInLists}
                columns={columns}
                storageKey="check-in-lists-table"
            />
            {(editModalOpen && selectedCheckInListId)
                && <EditCheckInListModal onClose={closeEditModal}
                                         checkInListId={selectedCheckInListId}/>}
        </>
    );
};
