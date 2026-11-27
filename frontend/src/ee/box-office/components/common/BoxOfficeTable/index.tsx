import {Button, Tooltip, VisuallyHidden} from '@mantine/core';
import {CreateButton} from "../../../../../components/common/CreateButton";
import {useAccess} from "../../../../../hooks/useAccess.ts";
import {BoxOffice, Event, EventType, IdParam} from "../../../../../types.ts";
import {IconCashRegister, IconChartBar, IconCopy, IconKey, IconPencil, IconPlus, IconTrash} from "@tabler/icons-react";
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
import {ActionMenu, RowActionsTarget} from '../../../../../components/common/ActionMenu';
import {CellLink, CellText, EmptyCell, IdentityCell} from "../../../../../components/common/TanStackTable/cells.tsx";
import {StatusBadge} from "../../../../../components/common/StatusBadge";
import {CellContext} from "@tanstack/react-table";
import {formatDateWithLocale} from "../../../../../utilites/dates.ts";
import {formatCurrency} from "../../../../../utilites/currency.ts";
import classes from './BoxOfficeTable.module.scss';

interface BoxOfficeTableProps {
    boxOffices: BoxOffice[];
    openCreateModal: () => void;
    isRefreshing?: boolean;
    event?: Event;
    isLocked?: boolean;
}

export const boxOfficeUrl = (shortId: string) => `${window.location.origin}/box-office/${shortId}`;

export const copyBoxOfficeLink = (shortId: string) => {
    navigator.clipboard.writeText(boxOfficeUrl(shortId)).then(() => {
        showSuccess(t`Box office link copied to clipboard`);
    });
};

export const BoxOfficeTable = ({boxOffices, openCreateModal, event, isLocked = false, isRefreshing}: BoxOfficeTableProps) => {
    const [editModalOpen, {open: openEditModal, close: closeEditModal}] = useDisclosure(false);
    const [selectedBoxOfficeId, setSelectedBoxOfficeId] = useState<IdParam>();
    const [statsBoxOffice, setStatsBoxOffice] = useState<BoxOffice | null>(null);
    const canViewFinancials = useAccess().can('VIEW_FINANCIALS');
    const deleteMutation = useDeleteBoxOffice();
    const {generatePin, openBoxOffice, pinModalElement} = useBoxOfficePin();
    const {eventId} = useParams();
    const isRecurring = event?.type === EventType.RECURRING;
    const isMobile = useMediaQuery('(max-width: 62em)');

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
                    meta: {sticky: 'left'},
                    cell: ({row: {original: boxOffice}}: CellContext<BoxOffice, unknown>) => (
                        <IdentityCell
                            stacked
                            secondary={!boxOffice.products || boxOffice.products.length === 0
                                ? t`Sells every product`
                                : boxOffice.products.length === 1
                                    ? t`Sells 1 product`
                                    : <Trans>Sells {boxOffice.products.length} products</Trans>}
                            primary={isLocked ? (
                                <CellText strong title={boxOffice.name}>{boxOffice.name}</CellText>
                            ) : (
                                <CellLink
                                    strong
                                    title={boxOffice.name}
                                    onClick={() => {
                                        setSelectedBoxOfficeId(boxOffice.id as IdParam);
                                        openEditModal();
                                    }}
                                >
                                    {boxOffice.name}
                                </CellLink>
                            )}
                            trailing={(
                                <>
                                    {boxOffice.is_system_default && <StatusBadge tone="neutral">{t`Default`}</StatusBadge>}
                                    {!boxOffice.has_pin && (
                                        <Tooltip label={t`Staff can't sign in until a PIN is set`}>
                                            <span><StatusBadge tone="waiting">{t`No PIN`}</StatusBadge></span>
                                        </Tooltip>
                                    )}
                                </>
                            )}
                        />
                    ),
                },
                {
                    id: 'occurrence',
                    header: t`Date`,
                    cell: ({row: {original: boxOffice}}: CellContext<BoxOffice, unknown>) => {
                        const occurrence = boxOffice.event_occurrence;

                        if (!occurrence || !event?.timezone) {
                            return <CellText muted>{t`Any date`}</CellText>;
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
                    id: 'orders',
                    header: t`Orders`,
                    meta: {align: 'right'},
                    cell: ({row: {original: boxOffice}}: CellContext<BoxOffice, unknown>) => boxOffice.sales_count === 1
                        ? t`1 order`
                        : <Trans>{boxOffice.sales_count} orders</Trans>,
                },
                ...(canViewFinancials ? [{
                    id: 'sales',
                    header: t`Sales`,
                    meta: {align: 'right'},
                    cell: ({row: {original: boxOffice}}: CellContext<BoxOffice, unknown>) => boxOffice.gross_sales !== undefined
                        ? <CellText strong>{formatCurrency(boxOffice.gross_sales, boxOffice.currency ?? event?.currency)}</CellText>
                        : <EmptyCell/>,
                } satisfies TanStackTableColumn<BoxOffice>] : []),
                {
                    id: 'status',
                    header: t`Status`,
                    cell: ({row: {original: boxOffice}}: CellContext<BoxOffice, unknown>) => {
                        const isActive = !boxOffice.is_expired && boxOffice.is_active;

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
                    cell: (info: CellContext<BoxOffice, unknown>) => {
                        const boxOffice = info.row.original;
                        const setupItems = [
                            {
                                label: t`Edit Box Office`,
                                requiresEdit: true,
                                icon: <IconPencil size={14}/>,
                                onClick: () => {
                                    setSelectedBoxOfficeId(boxOffice.id as IdParam);
                                    openEditModal();
                                },
                                dataTestId: 'box-office-edit-menu-item',
                            },
                            {
                                label: boxOffice.has_pin ? t`Reset PIN` : t`Set PIN`,
                                requiresEdit: true,
                                icon: <IconKey size={14}/>,
                                onClick: () => generatePin(boxOffice),
                                dataTestId: 'box-office-reset-pin-menu-item',
                            },
                            {
                                label: t`Open Box Office`,
                                requiresEdit: true,
                                icon: <IconCashRegister size={14}/>,
                                onClick: () => openBoxOffice(boxOffice),
                                visible: isMobile,
                            },
                        ];
                        const manageItems = [
                            ...(isLocked ? [] : setupItems),
                            ...(canViewFinancials ? [{
                                label: t`Sales summary`,
                                icon: <IconChartBar size={14}/>,
                                onClick: () => setStatsBoxOffice(boxOffice),
                                dataTestId: 'box-office-stats-menu-item',
                            }] : []),
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
                                        requiresEdit: true,
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
                                        size="compact-sm"
                                        variant="default"
                                        leftSection={<IconCashRegister size={14}/>}
                                        onClick={() => openBoxOffice(boxOffice)}
                                        data-testid="box-office-open-button"
                                    >
                                        {t`Open Box Office`}
                                    </Button>
                                )}
                                <ActionMenu itemsGroups={groups} dataTestId="box-office-actions-menu" target={<RowActionsTarget/>}/>
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
        [eventId, isRecurring, event?.timezone, event?.currency, isMobile, isLocked, canViewFinancials]
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
                            <CreateButton
                                size={'xs'}
                                leftSection={<IconPlus/>}
                                color={'green'}
                                onClick={() => openCreateModal()}>{t`Create Box Office`}
                            </CreateButton>
                        )}
                    </>
                )}
            />
        );
    }

    return (
        <>
            <TanStackTable rowLines={2}
                data={boxOffices}
                columns={columns}
                isRefreshing={isRefreshing}
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
