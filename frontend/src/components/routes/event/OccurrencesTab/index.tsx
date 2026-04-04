import {useNavigate, useParams} from "react-router";
import {t} from "@lingui/macro";
import {Anchor, Button, Checkbox, Group, Menu, Paper, Progress, SegmentedControl, Stack, Text, Tooltip} from "@mantine/core";
import {
    IconCalendar,
    IconCalendarEvent,
    IconCalendarPlus,
    IconDotsVertical,
    IconList,
    IconPencil,
    IconPlus,
} from "@tabler/icons-react";
import {useDisclosure} from "@mantine/hooks";
import {useCallback, useMemo, useRef, useState} from "react";
import {modals} from "@mantine/modals";
import {PageBody} from "../../../common/PageBody";
import {PageTitle} from "../../../common/PageTitle";
import {Pagination} from "../../../common/Pagination";
import {TableSkeleton} from "../../../common/TableSkeleton";
import {useGetEventOccurrences} from "../../../../queries/useGetEventOccurrences.ts";
import {useFilterQueryParamSync} from "../../../../hooks/useFilterQueryParamSync.ts";
import {EventOccurrence, EventOccurrenceStatus, MessageType, QueryFilterFields, QueryFilterOperator, QueryFilters} from "../../../../types.ts";
import {useGetEvent} from "../../../../queries/useGetEvent.ts";
import {formatDateWithLocale} from "../../../../utilites/dates.ts";
import {formatCurrency} from "../../../../utilites/currency.ts";
import {OccurrenceEditModal} from "./OccurrenceEditModal";
import {OccurrenceBulkEditModal} from "./OccurrenceBulkEditModal";
import {RecurrenceScheduleModal} from "./RecurrenceScheduleModal";
import {CalendarView} from "./CalendarView";
import {useCancelOccurrence} from "../../../../mutations/useCancelOccurrence.ts";
import {useDeleteEventOccurrence} from "../../../../mutations/useDeleteEventOccurrence.ts";
import {useBulkUpdateOccurrences} from "../../../../mutations/useBulkUpdateOccurrences.ts";
import {useUpdateEventOccurrence} from "../../../../mutations/useUpdateEventOccurrence.ts";
import {confirmationDialog} from "../../../../utilites/confirmationDialog.tsx";
import {showError, showSuccess} from "../../../../utilites/notifications.tsx";
import {GroupedOccurrenceTable, GroupedTableColumn} from "./GroupedOccurrenceTable";
import {OccurrenceMenuItems, OccurrenceMenuActions, statusLabel, StatusIcon} from "./OccurrenceMenu";
import {ManageOccurrenceModal} from "../../../modals/ManageOccurrenceModal";
import {SendMessageModal} from "../../../modals/SendMessageModal";
import {ShareModal} from "../../../modals/ShareModal";
import {CreateCheckInListModal} from "../../../modals/CreateCheckInListModal";
import {useGetEventCheckInLists} from "../../../../queries/useGetCheckInLists.ts";
import {eventHomepageUrl} from "../../../../utilites/urlHelper.ts";
import classes from './OccurrencesTab.module.scss';

const OccurrencesTab = () => {
    const {eventId} = useParams();
    const navigate = useNavigate();
    const [searchParams, setSearchParams] = useFilterQueryParamSync();
    const viewModeKey = `occurrences_view_${eventId}`;
    const [viewMode, setViewMode] = useState<'list' | 'calendar'>(() => {
        if (typeof window === 'undefined') return 'list';
        const saved = localStorage.getItem(viewModeKey);
        return saved === 'calendar' ? 'calendar' : 'list';
    });

    const handleViewModeChange = (val: string) => {
        const mode = val as 'list' | 'calendar';
        setViewMode(mode);
        setSelectedIds(new Set());
        setSlideoutOccurrenceId(undefined);
        localStorage.setItem(viewModeKey, mode);
    };
    const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());

    const timePeriod = useMemo(() => {
        const tp = searchParams.filterFields?.time_period;
        if (!tp) return 'upcoming';
        const val = Array.isArray(tp) ? tp[0]?.value : tp.value;
        return (val as string) || 'upcoming';
    }, [searchParams.filterFields?.time_period]);

    const perPage = viewMode === 'calendar' ? 200 : (searchParams.perPage || 50);

    const queryParams: QueryFilters = useMemo(() => {
        const filterFields: QueryFilterFields = {...searchParams.filterFields};
        if (viewMode === 'calendar' || timePeriod === 'all') {
            delete filterFields.time_period;
        } else if (!filterFields.time_period) {
            filterFields.time_period = {operator: QueryFilterOperator.Equals, value: 'upcoming'};
        }
        return {...searchParams, perPage, filterFields};
    }, [searchParams, perPage, timePeriod, viewMode]);
    const occurrencesQuery = useGetEventOccurrences(eventId, queryParams);
    const occurrences = occurrencesQuery?.data?.data;
    const pagination = occurrencesQuery?.data?.meta;
    const {data: event} = useGetEvent(eventId);

    const [editModalOpen, {open: openEditModal, close: closeEditModal}] = useDisclosure(false);
    const [bulkEditOpen, {open: openBulkEdit, close: closeBulkEdit}] = useDisclosure(false);
    const [generateOpen, {open: openGenerate, close: closeGenerate}] = useDisclosure(false);
    const [selectedOccurrenceId, setSelectedOccurrenceId] = useState<number | undefined>();
    const [slideoutOccurrenceId, setSlideoutOccurrenceId] = useState<number | undefined>();
    const [duplicateFrom, setDuplicateFrom] = useState<EventOccurrence | undefined>();
    const [defaultDate, setDefaultDate] = useState<string | undefined>();
    const [messageOccurrenceId, setMessageOccurrenceId] = useState<number | undefined>();
    const [shareOccurrence, setShareOccurrence] = useState<EventOccurrence | undefined>();
    const [createCheckInForOccurrenceId, setCreateCheckInForOccurrenceId] = useState<number | undefined>();

    const checkInListsQuery = useGetEventCheckInLists(eventId);
    const checkInLists = checkInListsQuery?.data?.data;

    const cancelMutation = useCancelOccurrence();
    const deleteMutation = useDeleteEventOccurrence();
    const bulkUpdateMutation = useBulkUpdateOccurrences();
    const updateMutation = useUpdateEventOccurrence();
    const refundRef = useRef(false);

    const handleEditClick = (occurrenceId: number) => {
        setSelectedOccurrenceId(occurrenceId);
        setDuplicateFrom(undefined);
        openEditModal();
    };

    const handleEditClose = () => {
        setSelectedOccurrenceId(undefined);
        setDuplicateFrom(undefined);
        setDefaultDate(undefined);
        closeEditModal();
    };

    const handleCreateClick = () => {
        setSelectedOccurrenceId(undefined);
        setDuplicateFrom(undefined);
        setDefaultDate(undefined);
        openEditModal();
    };

    const handleCreateWithDate = (date: string) => {
        setSelectedOccurrenceId(undefined);
        setDuplicateFrom(undefined);
        setDefaultDate(date);
        openEditModal();
    };

    const handleDuplicate = (occ: EventOccurrence) => {
        setSelectedOccurrenceId(undefined);
        setDuplicateFrom(occ);
        openEditModal();
    };

    const handleCancel = (occurrenceId: number) => {
        refundRef.current = false;
        const occ = occurrences?.find(o => o.id === occurrenceId);
        const orderCount = occ?.statistics?.orders_created ?? 0;
        modals.openConfirmModal({
            title: t`Cancel Date`,
            children: (
                <>
                    <Text size="sm" mb="md">
                        {t`Are you sure you want to cancel this date? Affected attendees will be notified by email.`}
                    </Text>
                    {orderCount > 0 && (
                        <Text size="sm" fw={600} c="red" mb="md">
                            {t`This date has ${orderCount} order(s) that will be affected.`}
                        </Text>
                    )}
                    <Checkbox
                        label={t`Refund all orders for this date`}
                        description={t`Orders spanning multiple dates will be flagged for manual review.`}
                        onChange={(e) => { refundRef.current = e.currentTarget.checked; }}
                    />
                </>
            ),
            labels: {confirm: t`Cancel Date`, cancel: t`Go Back`},
            confirmProps: {color: 'red'},
            onConfirm: () => {
                cancelMutation.mutate({eventId, occurrenceId, refundOrders: refundRef.current}, {
                    onSuccess: () => showSuccess(t`Date cancelled`),
                    onError: (error: any) => showError(error?.response?.data?.message || t`Failed to cancel date`),
                });
            },
        });
    };

    const handleDelete = (occurrenceId: number) => {
        confirmationDialog(t`Are you sure you want to delete this date? This action cannot be undone.`, () => {
            deleteMutation.mutate({eventId, occurrenceId}, {
                onSuccess: () => showSuccess(t`Date deleted`),
                onError: (error: any) => showError(error?.response?.data?.message || t`Failed to delete date`),
            });
        });
    };

    const handleReactivate = (occ: EventOccurrence) => {
        confirmationDialog(t`Reactivate this date? It will be reopened for future sales.`, () => {
            updateMutation.mutate({
                eventId,
                occurrenceId: occ.id,
                data: {start_date: occ.start_date, status: 'ACTIVE'},
            }, {
                onSuccess: () => showSuccess(t`Date reactivated`),
                onError: (error: any) => showError(error?.response?.data?.message || t`Failed to reactivate date`),
            });
        });
    };

    const handleBulkCancel = () => {
        const count = selectedIds.size;
        refundRef.current = false;
        modals.openConfirmModal({
            title: t`Cancel ${count} date(s)`,
            children: (
                <>
                    <Text size="sm" mb="md">
                        {t`Are you sure you want to cancel ${count} date(s)? Affected attendees will be notified by email.`}
                    </Text>
                    <Checkbox
                        label={t`Refund all orders for these dates`}
                        description={t`Orders spanning multiple dates will be flagged for manual review.`}
                        onChange={(e) => { refundRef.current = e.currentTarget.checked; }}
                    />
                </>
            ),
            labels: {confirm: t`Cancel ${count} date(s)`, cancel: t`Go Back`},
            confirmProps: {color: 'red'},
            onConfirm: () => {
                bulkUpdateMutation.mutate({
                    eventId,
                    data: {
                        action: 'cancel',
                        occurrence_ids: [...selectedIds],
                        future_only: false,
                        skip_overridden: false,
                        refund_orders: refundRef.current,
                    },
                }, {
                    onSuccess: (response) => {
                        showSuccess(t`Cancelled ${response.updated_count} date(s)`);
                        setSelectedIds(new Set());
                    },
                    onError: (error: any) => showError(error?.response?.data?.message || t`Failed to cancel dates`),
                });
            },
        });
    };

    const handleBulkDelete = () => {
        const count = selectedIds.size;
        confirmationDialog(t`Delete ${count} selected date(s)? Dates with orders will be skipped. This cannot be undone.`, () => {
            bulkUpdateMutation.mutate({
                eventId,
                data: {action: 'delete', occurrence_ids: [...selectedIds], future_only: false, skip_overridden: false},
            }, {
                onSuccess: (response) => {
                    showSuccess(t`Deleted ${response.updated_count} date(s)`);
                    setSelectedIds(new Set());
                },
                onError: (error: any) => showError(error?.response?.data?.message || t`Failed to delete dates`),
            });
        });
    };

    const handleCheckIn = useCallback((occurrenceId: number) => {
        const list = checkInLists?.find(l => l.event_occurrence_id === occurrenceId)
            || checkInLists?.find(l => !l.event_occurrence_id);

        if (list) {
            window.open(`/check-in/${list.short_id}`, '_blank');
        } else {
            setCreateCheckInForOccurrenceId(occurrenceId);
        }
    }, [checkInLists]);

    const handlePageChange = (value: number) => {
        setSelectedIds(new Set());
        setSearchParams({pageNumber: value});
    };

    const handleTimePeriodChange = (val: string) => {
        const newFilterFields: QueryFilterFields = {...(searchParams.filterFields || {})};
        newFilterFields.time_period = {operator: QueryFilterOperator.Equals, value: val};
        setSelectedIds(new Set());
        setSearchParams({...searchParams, filterFields: newFilterFields, pageNumber: 1} as QueryFilters, true);
    };

    const menuActions: OccurrenceMenuActions = {
        eventId: eventId!,
        onEdit: handleEditClick,
        onCancel: handleCancel,
        onDelete: handleDelete,
        onNavigate: navigate,
        onDuplicate: handleDuplicate,
        onMessage: (id: number) => setMessageOccurrenceId(id),
        onCheckIn: handleCheckIn,
        onReactivate: handleReactivate,
        onShare: (occ: EventOccurrence) => setShareOccurrence(occ),
    };

    const columns = useMemo<GroupedTableColumn[]>(
        () => [
            {
                id: 'time',
                header: t`Time`,
                render: (occ: EventOccurrence) => {
                    if (!event) return null;
                    const startTime = formatDateWithLocale(occ.start_date, 'timeOnly', event.timezone);
                    const endTime = occ.end_date
                        ? formatDateWithLocale(occ.end_date, 'timeOnly', event.timezone)
                        : null;
                    return (
                        <Anchor
                            className={classes.dateTimeLink}
                            onClick={() => setSlideoutOccurrenceId(occ.id as number)}
                        >
                            <div className={classes.dateTimePrimary}>
                                {startTime}{endTime && <> &mdash; {endTime}</>}
                                {occ.is_overridden && (
                                    <Tooltip label={t`Edited`} withArrow>
                                        <IconPencil size={12} className={classes.editedIcon}/>
                                    </Tooltip>
                                )}
                            </div>
                            {occ.label && (
                                <div className={classes.dateTimeMeta}>{occ.label}</div>
                            )}
                        </Anchor>
                    );
                },
                headerStyle: {minWidth: 160},
            },
            {
                id: 'status',
                header: t`Status`,
                render: (occ: EventOccurrence) => {
                    const isClickable = occ.status === EventOccurrenceStatus.ACTIVE
                        || occ.status === EventOccurrenceStatus.CANCELLED;
                    const tooltip = occ.status === EventOccurrenceStatus.ACTIVE
                        ? t`Click to cancel`
                        : occ.status === EventOccurrenceStatus.CANCELLED
                            ? t`Click to reactivate`
                            : undefined;

                    const handleStatusClick = () => {
                        if (occ.status === EventOccurrenceStatus.ACTIVE) {
                            handleCancel(occ.id as number);
                        } else if (occ.status === EventOccurrenceStatus.CANCELLED) {
                            handleReactivate(occ);
                        }
                    };

                    const badge = (
                        <div
                            className={classes.statusBadge}
                            data-status={occ.status}
                            data-clickable={isClickable || undefined}
                            onClick={isClickable ? handleStatusClick : undefined}
                        >
                            <StatusIcon status={occ.status}/>
                            {statusLabel(occ.status)}
                        </div>
                    );

                    if (tooltip) {
                        return (
                            <Tooltip label={tooltip} position="top" withArrow>
                                {badge}
                            </Tooltip>
                        );
                    }
                    return badge;
                },
                headerStyle: {minWidth: 120},
            },
            {
                id: 'ticketsSold',
                header: t`Sold`,
                render: (occ: EventOccurrence) => {
                    const used = occ.used_capacity ?? 0;
                    const total = occ.capacity;
                    const pct = total ? Math.min(100, Math.round((used / total) * 100)) : 0;

                    return (
                        <div className={classes.ticketsSold}>
                            <div className={classes.ticketsSoldNumbers}>
                                <span className={classes.ticketsSoldCount}>{used}</span>
                                {total != null && (
                                    <span className={classes.ticketsSoldTotal}> / {total}</span>
                                )}
                            </div>
                            {total != null && (
                                <Progress
                                    value={pct}
                                    size={4}
                                    radius="xl"
                                    color={pct >= 90 ? 'red' : pct >= 70 ? 'orange' : 'blue'}
                                    className={classes.ticketsProgress}
                                    style={used === 0 ? {opacity: 0.3} : undefined}
                                />
                            )}
                        </div>
                    );
                },
                headerStyle: {minWidth: 120},
            },
            {
                id: 'activity',
                header: t`Activity`,
                render: (occ: EventOccurrence) => {
                    const orders = occ.statistics?.orders_created ?? 0;
                    const gross = occ.statistics?.total_gross_sales ?? 0;
                    const refunded = occ.statistics?.total_refunded ?? 0;

                    if (orders === 0 && gross === 0) {
                        return null;
                    }

                    return (
                        <div className={classes.activity}>
                            <span className={classes.activityText}>
                                {orders} {orders === 1 ? t`order` : t`orders`}
                                {gross > 0 && (
                                    <> &middot; {formatCurrency(gross, event?.currency)}</>
                                )}
                            </span>
                            {refunded > 0 && (
                                <span className={classes.activityRefunded}>
                                    (-{formatCurrency(refunded, event?.currency)})
                                </span>
                            )}
                        </div>
                    );
                },
                headerStyle: {minWidth: 140},
            },
            {
                id: 'actions',
                header: '',
                sticky: 'right',
                render: (occ: EventOccurrence) => (
                    <Group wrap={'nowrap'} gap={0} justify={'flex-end'}>
                        <Menu shadow="md" width={200}>
                            <Menu.Target>
                                <div className={classes.action}>
                                    <Button size="xs" variant="transparent">
                                        <IconDotsVertical/>
                                    </Button>
                                </div>
                            </Menu.Target>
                            <Menu.Dropdown>
                                <OccurrenceMenuItems occurrence={occ} actions={menuActions}/>
                            </Menu.Dropdown>
                        </Menu>
                    </Group>
                ),
                headerStyle: {width: 70},
            },
        ],
        [event]
    );

    const rowStyleFn = useCallback((occ: EventOccurrence) => {
        return (timePeriod === 'all' && occ.is_past) ? {opacity: 0.5} : undefined;
    }, [timePeriod]);

    return (
        <PageBody>
            <PageTitle subheading={t`Manage dates and times for your recurring event`}>
                {t`Occurrence Schedule`}
            </PageTitle>

            <div className={classes.toolbar}>
                <SegmentedControl
                    size="sm"
                    value={viewMode}
                    onChange={handleViewModeChange}
                    data={[
                        {label: <Group gap={5} wrap="nowrap"><IconList size={13}/>{t`List`}</Group>, value: 'list'},
                        {label: <Group gap={5} wrap="nowrap"><IconCalendar size={13}/>{t`Calendar`}</Group>, value: 'calendar'},
                    ]}
                />

                {viewMode === 'list' && (
                    <>
                        <div className={classes.toolbarDivider}/>
                        <SegmentedControl
                            size="sm"
                            value={timePeriod}
                            onChange={handleTimePeriodChange}
                            data={[
                                {label: t`Upcoming`, value: 'upcoming'},
                                {label: t`Past`, value: 'past'},
                                {label: t`All`, value: 'all'},
                            ]}
                        />
                    </>
                )}

                <div className={classes.toolbarSpacer}/>

                {selectedIds.size > 0 && (
                    <div className={classes.selectionGroup}>
                        <span className={classes.selectionCount}>{selectedIds.size} {t`selected`}</span>
                        <button className={classes.selectionAction} data-danger onClick={handleBulkCancel}>
                            {t`Cancel`}
                        </button>
                        <button className={classes.selectionAction} data-danger onClick={handleBulkDelete}>
                            {t`Delete`}
                        </button>
                        <button className={classes.selectionAction} onClick={() => setSelectedIds(new Set())}>
                            {t`Clear`}
                        </button>
                        <div className={classes.toolbarDivider}/>
                    </div>
                )}

                <Button
                    size="sm"
                    variant="light"
                    leftSection={<IconPencil size={14}/>}
                    onClick={openBulkEdit}
                >
                    {t`Bulk Edit`}
                </Button>

                <Menu shadow="md" width={220}>
                    <Menu.Target>
                        <Button
                            size="sm"
                            leftSection={<IconPlus size={14}/>}
                        >
                            {t`Add Dates`}
                        </Button>
                    </Menu.Target>
                    <Menu.Dropdown>
                        <Menu.Item
                            leftSection={<IconCalendarEvent size={16}/>}
                            onClick={openGenerate}
                        >
                            {t`Set Up Schedule`}
                        </Menu.Item>
                        <Menu.Item
                            leftSection={<IconCalendarPlus size={16}/>}
                            onClick={handleCreateClick}
                        >
                            {t`Add a Single Date`}
                        </Menu.Item>
                    </Menu.Dropdown>
                </Menu>
            </div>

            <TableSkeleton isVisible={occurrencesQuery.isLoading}/>

            {occurrences && occurrencesQuery.isFetching && !occurrencesQuery.isLoading && (
                <Progress size={2} value={100} animated color="blue" mb="xs"/>
            )}

            {occurrences && occurrences.length === 0 && !occurrencesQuery.isFetching && (() => {
                const hasActiveFilters = !!(timePeriod === 'past' || timePeriod === 'all');
                return (
                    <Paper className={classes.emptyState} p="xl" radius="md" withBorder>
                        <Stack align="center" gap="md">
                            <img
                                src="/blank-slate/occurrence-schedule.svg"
                                alt=""
                                style={{width: 200, height: 200}}
                            />
                            <Stack align="center" gap={4}>
                                <Text fw={600} size="lg">
                                    {hasActiveFilters ? t`No dates match your filters` : t`No dates scheduled yet`}
                                </Text>
                                <Text c="dimmed" size="sm" ta="center" maw={360}>
                                    {hasActiveFilters
                                        ? t`Try adjusting your filters to see more dates.`
                                        : t`Set up a recurring schedule to automatically create dates, or add them one at a time.`
                                    }
                                </Text>
                            </Stack>
                            {!hasActiveFilters && (
                                <Group gap="md" mt="sm">
                                    <Button
                                        variant="filled"
                                        leftSection={<IconCalendarEvent size={16}/>}
                                        onClick={openGenerate}
                                    >
                                        {t`Set Up Schedule`}
                                    </Button>
                                    <Button
                                        variant="subtle"
                                        color="gray"
                                        onClick={handleCreateClick}
                                    >
                                        {t`or add a single date`}
                                    </Button>
                                </Group>
                            )}
                        </Stack>
                    </Paper>
                );
            })()}

            {occurrences && occurrences.length > 0 && viewMode === 'list' && event && (
                <>
                    <GroupedOccurrenceTable
                        occurrences={occurrences}
                        columns={columns}
                        eventTimezone={event.timezone}
                        selectedIds={selectedIds}
                        onSelectionChange={setSelectedIds}
                        rowStyle={rowStyleFn}
                    />
                    {pagination && (
                        <Text className={classes.countText}>
                            {t`Showing ${pagination.from}–${pagination.to} of ${pagination.total}`}
                        </Text>
                    )}
                </>
            )}

            {occurrences && occurrences.length > 0 && viewMode === 'calendar' && event && (
                <CalendarView
                    occurrences={occurrences}
                    eventTimezone={event.timezone}
                    menuActions={menuActions}
                    onOccurrenceClick={(id) => setSlideoutOccurrenceId(id)}
                    onCreate={handleCreateWithDate}
                />
            )}

            {!!occurrences?.length && viewMode === 'list' && (
                <Pagination
                    value={searchParams.pageNumber}
                    onChange={handlePageChange}
                    total={Number(pagination?.last_page)}
                />
            )}

            {editModalOpen && (
                <OccurrenceEditModal
                    onClose={handleEditClose}
                    occurrenceId={selectedOccurrenceId}
                    duplicateFrom={duplicateFrom}
                    defaultDate={defaultDate}
                />
            )}

            {bulkEditOpen && (
                <OccurrenceBulkEditModal onClose={closeBulkEdit} occurrences={occurrences}/>
            )}

            {generateOpen && (
                <RecurrenceScheduleModal onClose={closeGenerate}/>
            )}

            {slideoutOccurrenceId && (
                <ManageOccurrenceModal
                    occurrenceId={slideoutOccurrenceId}
                    onClose={() => setSlideoutOccurrenceId(undefined)}
                />
            )}

            {messageOccurrenceId && (
                <SendMessageModal
                    onClose={() => setMessageOccurrenceId(undefined)}
                    messageType={MessageType.AllAttendees}
                    eventOccurrenceId={messageOccurrenceId}
                />
            )}

            {createCheckInForOccurrenceId && (
                <CreateCheckInListModal
                    onClose={() => setCreateCheckInForOccurrenceId(undefined)}
                    initialOccurrenceId={createCheckInForOccurrenceId}
                />
            )}

            {shareOccurrence && event && (
                <ShareModal
                    opened={!!shareOccurrence}
                    onClose={() => setShareOccurrence(undefined)}
                    url={`${eventHomepageUrl(event)}?occurrence_id=${shareOccurrence.id}`}
                    title={event.title}
                    shareText={`${event.title} — ${formatDateWithLocale(shareOccurrence.start_date, 'shortDateTime', event.timezone)}`}
                />
            )}
        </PageBody>
    );
};

export default OccurrencesTab;
