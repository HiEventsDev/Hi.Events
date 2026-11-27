import {Button, Progress, VisuallyHidden} from '@mantine/core';
import {CreateButton} from "../CreateButton";
import {CheckInList, Event, EventType, IdParam} from "../../../types.ts";
import {IconCopy, IconPencil, IconPlus, IconQrcode, IconTrash} from "@tabler/icons-react";
import {useMemo, useState} from "react";
import {useDisclosure, useMediaQuery} from "@mantine/hooks";
import {useParams} from "react-router";
import {t, Trans} from "@lingui/macro";
import {NoResultsSplash} from "../NoResultsSplash";
import {EditCheckInListModal} from "../../modals/EditCheckInListModal";
import {useDeleteCheckInList} from "../../../mutations/useDeleteCheckInList";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";
import {confirmationDialog} from "../../../utilites/confirmationDialog.tsx";
import {TanStackTable, TanStackTableColumn} from "../TanStackTable";
import {ActionMenu, RowActionsTarget} from '../ActionMenu';
import {CellLink, CellText, IdentityCell} from "../TanStackTable/cells.tsx";
import {StatusBadge} from "../StatusBadge";
import {CellContext} from "@tanstack/react-table";
import {formatDateWithLocale} from "../../../utilites/dates.ts";
import classes from './CheckInListTable.module.scss';

interface CheckInListTableProps {
    checkInLists: CheckInList[];
    openCreateModal: () => void;
    isRefreshing?: boolean;
    event?: Event;
}

export const checkInListUrl = (shortId: string) => `${window.location.origin}/check-in/${shortId}`;

export const copyCheckInListLink = (shortId: string) => {
    navigator.clipboard.writeText(checkInListUrl(shortId)).then(() => {
        showSuccess(t`Check-In URL copied to clipboard`);
    });
};

export const CheckInListTable = ({checkInLists, openCreateModal, event, isRefreshing}: CheckInListTableProps) => {
    const [editModalOpen, {open: openEditModal, close: closeEditModal}] = useDisclosure(false);
    const [selectedCheckInListId, setSelectedCheckInListId] = useState<IdParam>();
    const deleteMutation = useDeleteCheckInList();
    const {eventId} = useParams();
    const isRecurring = event?.type === EventType.RECURRING;
    const isMobile = useMediaQuery('(max-width: 62em)');

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
                    meta: {sticky: 'left'},
                    cell: ({row: {original: list}}: CellContext<CheckInList, unknown>) => (
                        <IdentityCell
                            stacked
                            secondary={!list.products || list.products.length === 0
                                ? t`Covers every ticket`
                                : list.products.length === 1
                                    ? t`Includes 1 product`
                                    : <Trans>Includes {list.products.length} products</Trans>}
                            primary={(
                                <CellLink
                                    strong
                                    title={list.name}
                                    onClick={() => {
                                        setSelectedCheckInListId(list.id as IdParam);
                                        openEditModal();
                                    }}
                                >
                                    {list.name}
                                </CellLink>
                            )}
                            trailing={list.is_system_default && <StatusBadge tone="neutral">{t`Default`}</StatusBadge>}
                        />
                    ),
                },
                {
                    id: 'occurrence',
                    header: t`Date`,
                    cell: ({row: {original: list}}: CellContext<CheckInList, unknown>) => {
                        const occurrence = list.event_occurrence;

                        if (!occurrence || !event?.timezone) {
                            return <CellText muted>{t`All Dates`}</CellText>;
                        }

                        return (
                            <CellText maxWidth={240}>
                                {formatDateWithLocale(occurrence.start_date, 'shortDate', event.timezone)}
                                {' '}
                                {formatDateWithLocale(occurrence.start_date, 'timeOnly', event.timezone)}
                                {occurrence.label && ` · ${occurrence.label}`}
                            </CellText>
                        );
                    },
                },
                {
                    id: 'progress',
                    header: t`Check-Ins`,
                    cell: ({row: {original: list}}: CellContext<CheckInList, unknown>) => {
                        const percentage = list.total_attendees === 0
                            ? 0
                            : (list.checked_in_attendees / list.total_attendees) * 100;

                        return (
                            <span className={classes.progress}>
                                <Progress
                                    className={classes.progressBar}
                                    value={percentage}
                                    radius="xl"
                                    color={list.checked_in_attendees === list.total_attendees && list.total_attendees > 0 ? 'primary' : 'green'}
                                    size="sm"
                                />
                                {list.checked_in_attendees} / {list.total_attendees}
                            </span>
                        );
                    },
                },
                {
                    id: 'status',
                    header: t`Status`,
                    cell: ({row: {original: list}}: CellContext<CheckInList, unknown>) => {
                        const isActive = !list.is_expired && list.is_active;

                        return (
                            <StatusBadge tone={isActive ? 'positive' : 'neutral'}>
                                {isActive ? t`Active` : t`Inactive`}
                            </StatusBadge>
                        );
                    },
                },
                {
                    id: 'actions',
                    header: () => <VisuallyHidden>{t`Actions`}</VisuallyHidden>,
                    enableHiding: false,
                    cell: (info: CellContext<CheckInList, unknown>) => {
                        const list = info.row.original;
                        const manageItems = [
                            {
                                label: t`Edit Check-In List`,
                                requiresEdit: true,
                                icon: <IconPencil size={14}/>,
                                onClick: () => {
                                    setSelectedCheckInListId(list.id as IdParam);
                                    openEditModal();
                                }
                            },
                            {
                                label: t`Copy Check-In URL`,
                                icon: <IconCopy size={14}/>,
                                onClick: () => copyCheckInListLink(list.short_id),
                            },
                            {
                                label: t`Open Check-In Page`,
                                icon: <IconQrcode size={14}/>,
                                onClick: () => window.open(checkInListUrl(list.short_id), '_blank'),
                                visible: isMobile,
                            },
                        ];
                        const groups: {label: string; items: any[]}[] = [
                            {label: t`Manage`, items: manageItems},
                        ];
                        if (!list.is_system_default) {
                            groups.push({
                                label: t`Danger zone`,
                                items: [
                                    {
                                        label: t`Delete Check-In List`,
                                        requiresEdit: true,
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
                            });
                        }
                        return (
                            <div className={classes.rowActions}>
                                {!isMobile && (
                                    <Button
                                        size="compact-sm"
                                        variant="default"
                                        leftSection={<IconQrcode size={14}/>}
                                        onClick={() => window.open(checkInListUrl(list.short_id), '_blank')}
                                        data-testid="check-in-list-open-button"
                                    >
                                        {t`Open Check-In`}
                                    </Button>
                                )}
                                <ActionMenu itemsGroups={groups} dataTestId="check-in-list-actions-menu" target={<RowActionsTarget/>}/>
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
        [eventId, isRecurring, event?.timezone, isMobile]
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
                                A check-in list lets staff scan tickets at the door on a phone, tablet, or USB scanner. Share the link with your team, no account needed.
                            </Trans>
                        </p>
                        <CreateButton
                            size={'xs'}
                            leftSection={<IconPlus/>}
                            color={'green'}
                            onClick={() => openCreateModal()}>{t`Create Check-In List`}
                        </CreateButton>
                    </>
                )}
            />
        );
    }

    return (
        <>
            <TanStackTable rowLines={2}
                data={checkInLists}
                columns={columns}
                isRefreshing={isRefreshing}
            />
            {(editModalOpen && selectedCheckInListId)
                && <EditCheckInListModal onClose={closeEditModal}
                                         checkInListId={selectedCheckInListId}/>}
        </>
    );
};
