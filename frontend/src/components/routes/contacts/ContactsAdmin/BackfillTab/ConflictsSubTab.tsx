import {t} from "@lingui/macro";
import {useMemo, useState} from "react";
import {Alert, Badge, Button, Checkbox, Group, Select, Switch, Table, Text, TextInput, Tooltip} from "@mantine/core";
import {IconCheck, IconEyeOff, IconSearch} from "@tabler/icons-react";
import {Card} from "../../../../common/Card";
import {Pagination} from "../../../../common/Pagination";
import {SortableTh} from "../../../../common/SortableTh";
import {TableSkeleton} from "../../../../common/TableSkeleton";
import {QueryFilterOperator, QueryFilters} from "../../../../../types.ts";
import {useGetEvents} from "../../../../../queries/useGetEvents.ts";
import {useGetBackfillConflicts} from "../../../../../queries/useGetBackfillConflicts.ts";
import {useApplyConflictDecisions} from "../../../../../mutations/useApplyConflictDecisions.ts";
import {useRowSelection} from "../../../../../hooks/useRowSelection.ts";
import {showError, showSuccess} from "../../../../../utilites/notifications.tsx";

const formatValue = (value: unknown): string => {
    if (value === null || value === undefined) return '-';
    if (Array.isArray(value)) return value.join(', ');
    if (typeof value === 'object') return JSON.stringify(value);
    return String(value);
};

export const ConflictsSubTab = () => {
    const [page, setPage] = useState(1);
    const [query, setQuery] = useState('');
    const [eventFilter, setEventFilter] = useState<string | null>(null);
    const [sortBy, setSortBy] = useState('contact_email');
    const [sortDir, setSortDir] = useState('asc');
    const [showProcessed, setShowProcessed] = useState(false);

    const applyMutation = useApplyConflictDecisions();
    const eventsQuery = useGetEvents({pageNumber: 1, perPage: 100});
    const eventOptions = useMemo(
        () => (eventsQuery.data?.data ?? []).map((e: any) => ({value: String(e.id), label: e.title})),
        [eventsQuery.data],
    );

    const handleSort = (field: string) => {
        if (sortBy === field) setSortDir(sortDir === 'asc' ? 'desc' : 'asc');
        else { setSortBy(field); setSortDir('asc'); }
        setPage(1);
    };

    const filterFields: Record<string, any> = {};
    if (eventFilter) filterFields.event_id = {operator: QueryFilterOperator.Equals, value: eventFilter};

    const params: QueryFilters = {
        pageNumber: page,
        perPage: 25,
        query: query || undefined,
        filterFields: Object.keys(filterFields).length > 0 ? filterFields : undefined,
        sortBy,
        sortDirection: sortDir,
    };

    const result = useGetBackfillConflicts(params, showProcessed);
    const rows = result.data?.data;
    const meta = result.data?.meta;

    const activeIdsInOrder = useMemo(
        () => (rows ?? []).filter((r) => !r.processed).map((r) => r.question_answer_id),
        [rows],
    );
    const selection = useRowSelection<number>(activeIdsInOrder);

    const selectedIds = useMemo(
        () => (rows ?? [])
            .filter((r) => !r.processed && selection.isSelected(r.question_answer_id))
            .map((r) => r.question_answer_id),
        [rows, selection],
    );

    const allActiveSelected = activeIdsInOrder.length > 0
        && activeIdsInOrder.every((id) => selection.isSelected(id));

    const applyDecision = (decision: 'update' | 'ignore') => {
        if (selectedIds.length === 0) return;
        const decisions = selectedIds.map((id) => ({question_answer_id: id, decision}));
        applyMutation.mutate({decisions}, {
            onSuccess: (res) => {
                if (decision === 'update') {
                    showSuccess(t`Updated ${res.data.count} answer(s).`);
                } else {
                    showSuccess(t`Ignored ${res.data.count} answer(s).`);
                }
                selection.clear();
            },
            onError: () => showError(t`Could not apply decisions.`),
        });
    };

    return (
        <Card>
            <Group gap="sm" wrap="wrap" mb="md" align="center">
                <TextInput
                    placeholder={t`Search by email or attribute...`}
                    leftSection={<IconSearch size={16}/>}
                    value={query}
                    onChange={(e) => { setQuery(e.currentTarget.value); setPage(1); }}
                    size="sm"
                    style={{flex: 1, minWidth: 220, marginBottom: 0}}
                />
                <Select
                    placeholder={t`Filter by event`}
                    data={eventOptions}
                    value={eventFilter}
                    onChange={(val) => { setEventFilter(val); setPage(1); }}
                    clearable
                    searchable
                    size="sm"
                    style={{width: 220, marginBottom: 0}}
                />
                <Switch
                    label={t`Show processed`}
                    checked={showProcessed}
                    onChange={(e) => { setShowProcessed(e.currentTarget.checked); setPage(1); }}
                    size="sm"
                    style={{marginBottom: 0}}
                />
                <Button
                    size="sm"
                    leftSection={<IconCheck size={14}/>}
                    disabled={selectedIds.length === 0}
                    loading={applyMutation.isPending && applyMutation.variables?.decisions?.[0]?.decision === 'update'}
                    onClick={() => applyDecision('update')}
                >
                    {selectedIds.length > 0 ? t`Update (${selectedIds.length})` : t`Update`}
                </Button>
                <Button
                    size="sm"
                    variant="default"
                    leftSection={<IconEyeOff size={14}/>}
                    disabled={selectedIds.length === 0}
                    loading={applyMutation.isPending && applyMutation.variables?.decisions?.[0]?.decision === 'ignore'}
                    onClick={() => applyDecision('ignore')}
                >
                    {t`Ignore`}
                </Button>
            </Group>

            <Text size="sm" c="dimmed" mb="md">
                {t`Event answers that don't match the contact's current value — either the attribute was empty, or the stored value differs. Select rows and click Update to write the event answer onto the contact, or click Ignore to keep the existing value. Both outcomes mark the rows as processed so they won't reappear.`}
            </Text>

            {result.isLoading && <TableSkeleton isVisible/>}

            {!!result.error && (
                <Alert color="red" radius="md">{t`Failed to load conflicts`}</Alert>
            )}

            {!result.isLoading && !result.error && rows && rows.length === 0 && (
                <Text c="dimmed" ta="center" py="xl">
                    {showProcessed
                        ? t`No conflicts.`
                        : t`No unresolved conflicts. Toggle "Show processed" to see previously evaluated entries.`}
                </Text>
            )}

            {rows && rows.length > 0 && (
                <>
                    <Table striped highlightOnHover>
                        <Table.Thead>
                            <Table.Tr>
                                <Table.Th style={{width: 36}}>
                                    <Checkbox
                                        aria-label={t`Select all`}
                                        checked={allActiveSelected}
                                        indeterminate={selection.count > 0 && !allActiveSelected}
                                        disabled={activeIdsInOrder.length === 0}
                                        onChange={() => {
                                            if (selection.count > 0) selection.clear();
                                            else selection.selectAll();
                                        }}
                                    />
                                </Table.Th>
                                <SortableTh label={t`Contact`} field="contact_email" sortBy={sortBy} sortDir={sortDir} onSort={handleSort}/>
                                <SortableTh label={t`Attribute`} field="attribute_name" sortBy={sortBy} sortDir={sortDir} onSort={handleSort}/>
                                <Table.Th>{t`Event Answer`}</Table.Th>
                                <Table.Th>{t`Contact Default`}</Table.Th>
                                <SortableTh label={t`Event`} field="event_title" sortBy={sortBy} sortDir={sortDir} onSort={handleSort}/>
                                <Table.Th style={{width: 130}}>{t`Status`}</Table.Th>
                            </Table.Tr>
                        </Table.Thead>
                        <Table.Tbody>
                            {rows.map((row) => {
                                const rowStyle = row.processed ? {opacity: 0.55} : undefined;
                                return (
                                    <Table.Tr key={row.question_answer_id} style={rowStyle}>
                                        <Table.Td>
                                            {!row.processed && (
                                                <Checkbox
                                                    aria-label={t`Select row`}
                                                    checked={selection.isSelected(row.question_answer_id)}
                                                    onMouseDown={(e) => selection.captureShift(e.shiftKey)}
                                                    onKeyDown={(e) => selection.captureShift(e.shiftKey)}
                                                    onChange={() => selection.toggleWithStoredShift(row.question_answer_id)}
                                                />
                                            )}
                                        </Table.Td>
                                        <Table.Td>{row.contact_email}</Table.Td>
                                        <Table.Td>{row.attribute_name}</Table.Td>
                                        <Table.Td>{formatValue(row.proposed_value)}</Table.Td>
                                        <Table.Td>{formatValue(row.existing_value)}</Table.Td>
                                        <Table.Td>{row.event_title || '-'}</Table.Td>
                                        <Table.Td>
                                            {row.decision_applied === 'updated' && (
                                                <Tooltip
                                                    withArrow
                                                    label={row.applied_at
                                                        ? t`Applied ${new Date(row.applied_at).toLocaleString()}`
                                                        : t`Applied (timestamp not recorded for this row)`}
                                                >
                                                    <Badge size="sm" variant="light" color="green" style={{cursor: 'help'}}>
                                                        {t`Updated`}
                                                    </Badge>
                                                </Tooltip>
                                            )}
                                            {row.decision_applied === 'ignored' && (
                                                <Badge size="sm" variant="light" color="gray">{t`No Update`}</Badge>
                                            )}
                                            {row.decision_applied === null && row.processed && (
                                                <Badge size="sm" variant="light" color="gray">{t`Processed`}</Badge>
                                            )}
                                        </Table.Td>
                                    </Table.Tr>
                                );
                            })}
                        </Table.Tbody>
                    </Table>

                    {meta && Number(meta.last_page) > 1 && (
                        <Pagination value={page} onChange={setPage} total={Number(meta.last_page)}/>
                    )}
                </>
            )}
        </Card>
    );
};
