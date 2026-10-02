import {Anchor, Badge, Button, Tooltip} from '@mantine/core';
import {BoxOffice, Event, EventType, IdParam} from "../../../../../types.ts";
import {
    IconCalendarEvent,
    IconCashRegister,
    IconChartBar,
    IconCheck,
    IconCopy,
    IconKey,
    IconPencil,
    IconPlus,
    IconTrash,
    IconX,
} from "@tabler/icons-react";
import {useMemo, useState} from "react";
import {useDisclosure, useMediaQuery} from "@mantine/hooks";
import {useParams} from "react-router";
import {t, Trans} from "@lingui/macro";
import {NoResultsSplash} from "../../../../../components/common/NoResultsSplash";
import {EditBoxOfficeModal} from "../../modals/EditBoxOfficeModal";
import {BoxOfficeStatsModal} from "../../modals/BoxOfficeStatsModal";
import {useDeleteBoxOffice} from "../../../mutations/useDeleteBoxOffice.ts";
import {useBoxOfficePin} from "../../../hooks/useBoxOfficePin.tsx";
import {showError, showSuccess} from "../../../../../utilites/notifications.tsx";
import {confirmationDialog} from "../../../../../utilites/confirmationDialog.tsx";
import {TanStackTable, TanStackTableColumn} from "../../../../../components/common/TanStackTable";
import {ActionMenu} from '../../../../../components/common/ActionMenu';
import {CellContext} from "@tanstack/react-table";
import Truncate from "../../../../../components/common/Truncate";
import {formatDateWithLocale} from "../../../../../utilites/dates.ts";
import {formatCurrency} from "../../../../../utilites/currency.ts";
import classes from './BoxOfficeTable.module.scss';

interface BoxOfficeTableProps {
    boxOffices: BoxOffice[];
    openCreateModal: () => void;
    event?: Event;
    isLocked?: boolean;
}

export const boxOfficeUrl = (shortId: string) => `${window.location.origin}/box-office/${shortId}`;

export const copyBoxOfficeLink = (shortId: string) => {
    navigator.clipboard.writeText(boxOfficeUrl(shortId)).then(() => {
        showSuccess(t`Box office link copied to clipboard`);
    });
};

export const BoxOfficeTable = ({boxOffices, openCreateModal, event, isLocked = false}: BoxOfficeTableProps) => {
    const [editModalOpen, {open: openEditModal, close: closeEditModal}] = useDisclosure(false);
    const [selectedBoxOfficeId, setSelectedBoxOfficeId] = useState<IdParam>();
    const [statsBoxOffice, setStatsBoxOffice] = useState<BoxOffice | null>(null);
    const deleteMutation = useDeleteBoxOffice();
    const {generatePin, openBoxOffice, pinModalElement} = useBoxOfficePin();
    const {eventId} = useParams();
    const isRecurring = event?.type === EventType.RECURRING;
    const isMobile = useMediaQuery('(max-width: 768px)');

    const handleDelete = (boxOfficeId: IdParam, eventId: IdParam) => {
        deleteMutation.mutate({boxOfficeId, eventId}, {
            onSuccess: () => showSuccess(t`Box office deleted`),
            onError: (error: any) => showError(error?.response?.data?.message || error.message),
        });
    }

    const columns = useMemo<TanStackTableColumn<BoxOffice>[]>(
        () => {
            const allColumns: TanStackTableColumn<BoxOffice>[] = [
                {
                    id: 'name',
                    header: t`Box Office`,
                    enableHiding: false,
                    cell: (info: CellContext<BoxOffice, unknown>) => {
                        const boxOffice = info.row.original;
                        const sellsEverything = !boxOffice.products || boxOffice.products.length === 0;
                        return (
                            <div className={classes.listDetails}>
                                <div className={classes.nameRow}>
                                    {isLocked ? (
                                        <span className={classes.listName}><Truncate text={boxOffice.name} length={40}/></span>
                                    ) : (
                                        <Anchor
                                            className={classes.listName}
                                            onClick={() => {
                                                setSelectedBoxOfficeId(boxOffice.id as IdParam);
                                                openEditModal();
                                            }}
                                        >
                                            <Truncate text={boxOffice.name} length={40}/>
                                        </Anchor>
                                    )}
                                    {boxOffice.is_system_default && (
                                        <Badge size="xs" color="gray" variant="light">{t`Default`}</Badge>
                                    )}
                                    {!boxOffice.has_pin && (
                                        <Tooltip label={t`Staff can't sign in until a PIN is set`}>
                                            <Badge size="xs" color="orange" variant="light">{t`No PIN`}</Badge>
                                        </Tooltip>
                                    )}
                                </div>
                                <div className={classes.productsText}>
                                    {sellsEverything
                                        ? t`Sells every product`
                                        : boxOffice.products!.length === 1
                                            ? t`Sells 1 product`
                                            : <Trans>Sells {boxOffice.products!.length} products</Trans>
                                    }
                                </div>
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
                    cell: (info: CellContext<BoxOffice, unknown>) => {
                        const occurrence = info.row.original.event_occurrence;

                        if (!occurrence || !event?.timezone) {
                            return (
                                <div className={classes.occurrenceText}>
                                    {t`Any date`}
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
                    id: 'sales',
                    header: t`Sales`,
                    enableHiding: true,
                    cell: (info: CellContext<BoxOffice, unknown>) => {
                        const boxOffice = info.row.original;
                        return (
                            <div className={classes.salesContainer}>
                                <div className={classes.salesGross}>
                                    {formatCurrency(boxOffice.gross_sales, boxOffice.currency ?? event?.currency)}
                                </div>
                                <div className={classes.salesCount}>
                                    {boxOffice.sales_count === 1
                                        ? t`1 order`
                                        : <Trans>{boxOffice.sales_count} orders</Trans>}
                                </div>
                            </div>
                        );
                    },
                    meta: {
                        headerStyle: {minWidth: 120},
                    },
                },
                {
                    id: 'status',
                    header: t`Status`,
                    enableHiding: true,
                    cell: (info: CellContext<BoxOffice, unknown>) => {
                        const boxOffice = info.row.original;
                        const isActive = !boxOffice.is_expired && boxOffice.is_active;
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
                    cell: (info: CellContext<BoxOffice, unknown>) => {
                        const boxOffice = info.row.original;
                        const setupItems = [
                            {
                                label: t`Edit Box Office`,
                                icon: <IconPencil size={14}/>,
                                onClick: () => {
                                    setSelectedBoxOfficeId(boxOffice.id as IdParam);
                                    openEditModal();
                                },
                                dataTestId: 'box-office-edit-menu-item',
                            },
                            {
                                label: boxOffice.has_pin ? t`Reset PIN` : t`Set PIN`,
                                icon: <IconKey size={14}/>,
                                onClick: () => generatePin(boxOffice),
                                dataTestId: 'box-office-reset-pin-menu-item',
                            },
                            {
                                label: t`Open Box Office`,
                                icon: <IconCashRegister size={14}/>,
                                onClick: () => openBoxOffice(boxOffice),
                                visible: isMobile,
                            },
                        ];
                        const manageItems = [
                            ...(isLocked ? [] : setupItems),
                            {
                                label: t`Sales summary`,
                                icon: <IconChartBar size={14}/>,
                                onClick: () => setStatsBoxOffice(boxOffice),
                                dataTestId: 'box-office-stats-menu-item',
                            },
                            {
                                label: t`Copy Box Office Link`,
                                icon: <IconCopy size={14}/>,
                                onClick: () => copyBoxOfficeLink(boxOffice.short_id),
                            },
                        ];
                        const groups: { label: string; items: any[] }[] = [
                            {label: t`Manage`, items: manageItems},
                        ];
                        if (!boxOffice.is_system_default) {
                            groups.push({
                                label: t`Danger zone`,
                                items: [
                                    {
                                        label: t`Delete Box Office`,
                                        icon: <IconTrash size={14}/>,
                                        onClick: () => {
                                            confirmationDialog(
                                                t`Are you sure you would like to delete this box office?`,
                                                () => handleDelete(boxOffice.id as IdParam, eventId),
                                            )
                                        },
                                        color: 'red',
                                    },
                                ],
                            });
                        }
                        return (
                            <div className={classes.rowActions}>
                                {!isMobile && !isLocked && (
                                    <Button
                                        size="xs"
                                        variant="light"
                                        leftSection={<IconCashRegister size={14}/>}
                                        onClick={() => openBoxOffice(boxOffice)}
                                        data-testid="box-office-open-button"
                                    >
                                        {t`Open Box Office`}
                                    </Button>
                                )}
                                <ActionMenu itemsGroups={groups} dataTestId="box-office-actions-menu"/>
                            </div>
                        );
                    },
                    meta: {
                        sticky: 'right',
                    },
                },
            ];

            return allColumns.filter(column => !(column.id === 'occurrence' && !isRecurring));
        },
        [eventId, isRecurring, event?.timezone, event?.currency, isMobile, isLocked]
    );

    if (boxOffices.length === 0) {
        return (
            <NoResultsSplash
                heading={t`No Box Offices`}
                imageHref={'/blank-slate/check-in-lists.svg'}
                subHeading={(
                    <>
                        <p>
                            <Trans>
                                A box office lets staff sell tickets at the door with cash, comps, or a card reader. Share the link with your team, no account needed.
                            </Trans>
                        </p>
                        {!isLocked && (
                            <Button
                                size={'xs'}
                                leftSection={<IconPlus/>}
                                color={'green'}
                                onClick={() => openCreateModal()}>{t`Create Box Office`}
                            </Button>
                        )}
                    </>
                )}
            />
        );
    }

    return (
        <>
            <TanStackTable
                data={boxOffices}
                columns={columns}
                storageKey="box-offices-table"
            />
            {(editModalOpen && selectedBoxOfficeId)
                && <EditBoxOfficeModal onClose={closeEditModal} boxOfficeId={selectedBoxOfficeId}/>}
            {statsBoxOffice && eventId && (
                <BoxOfficeStatsModal eventId={eventId} boxOffice={statsBoxOffice} onClose={() => setStatsBoxOffice(null)}/>
            )}
            {pinModalElement}
        </>
    );
};
