import {t} from "@lingui/macro";
import {useMemo, useState} from "react";
import {Alert, Badge, Button, Group, SegmentedControl, Select, Switch, Table, Text, TextInput} from "@mantine/core";
import {IconSearch} from "@tabler/icons-react";
import {Card} from "../../../../common/Card";
import {Pagination} from "../../../../common/Pagination";
import {SortableTh} from "../../../../common/SortableTh";
import {TableSkeleton} from "../../../../common/TableSkeleton";
import {QueryFilterOperator, QueryFilters} from "../../../../../types.ts";
import {useGetEvents} from "../../../../../queries/useGetEvents.ts";
import {useGetBackfillConflicts} from "../../../../../queries/useGetBackfillConflicts.ts";
import {useApplyConflictDecisions} from "../../../../../mutations/useApplyConflictDecisions.ts";
import {showError, showSuccess} from "../../../../../utilites/notifications.tsx";

type Decision = 'update' | 'leave_alone';

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
    const [decisions, setDecisions] = useState<Map<number, Decision>>(new Map());

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

    const setDecision = (qaId: number, decision: Decision) => {
        setDecisions((prev) => {
            const next = new Map(prev);
            next.set(qaId, decision);
            return next;
        });
    };

    const pendingDecisions = useMemo(
        () => Array.from(decisions.entries()).map(([qaId, decision]) => ({
            question_answer_id: qaId,
            decision,
        })),
        [decisions],
    );

    const updateCount = useMemo(
        () => Array.from(decisions.values()).filter((d) => d === 'update').length,
        [decisions],
    );

    const handleApply = () => {
        if (pendingDecisions.length === 0) return;
        applyMutation.mutate({decisions: pendingDecisions}, {
            onSuccess: (res) => {
                showSuccess(t`Applied ${res.data.count} decision(s).`);
                setDecisions(new Map());
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
                />
                <Button
                    size="sm"
                    disabled={pendingDecisions.length === 0}
                    loading={applyMutation.isPending}
                    onClick={handleApply}
                >
                    {pendingDecisions.length > 0
                        ? t`Update (${updateCount} update, ${pendingDecisions.length - updateCount} leave)`
                        : t`Update`}
                </Button>
            </Group>

            <Text size="sm" c="dimmed" mb="md">
                {t`Event answers that don't match the contact's current value — either the attribute was empty, or the stored value differs. For each row, choose Update to write the event answer, or Leave alone to keep the existing value. Both outcomes mark the row as processed so it won't reappear.`}
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
                                <SortableTh label={t`Contact`} field="contact_email" sortBy={sortBy} sortDir={sortDir} onSort={handleSort}/>
                                <SortableTh label={t`Attribute`} field="attribute_name" sortBy={sortBy} sortDir={sortDir} onSort={handleSort}/>
                                <Table.Th>{t`Existing`}</Table.Th>
                                <Table.Th>{t`Event Answer`}</Table.Th>
                                <SortableTh label={t`Event`} field="event_title" sortBy={sortBy} sortDir={sortDir} onSort={handleSort}/>
                                <Table.Th style={{width: 200}}>{t`Decision`}</Table.Th>
                            </Table.Tr>
                        </Table.Thead>
                        <Table.Tbody>
                            {rows.map((row) => {
                                const current = decisions.get(row.question_answer_id) ?? null;
                                const existingStruck = current === 'update';
                                const proposedStruck = current === 'leave_alone' || current === null;
                                return (
                                    <Table.Tr key={row.question_answer_id} style={row.processed ? {opacity: 0.55} : undefined}>
                                        <Table.Td>{row.contact_email}</Table.Td>
                                        <Table.Td>{row.attribute_name}</Table.Td>
                                        <Table.Td style={existingStruck ? {textDecoration: 'line-through', opacity: 0.55} : undefined}>
                                            {formatValue(row.existing_value)}
                                        </Table.Td>
                                        <Table.Td style={proposedStruck ? {textDecoration: 'line-through', opacity: 0.55} : undefined}>
                                            {formatValue(row.proposed_value)}
                                        </Table.Td>
                                        <Table.Td>{row.event_title || '-'}</Table.Td>
                                        <Table.Td>
                                            {row.processed ? (
                                                <Badge size="sm" variant="light" color="gray">{t`Processed`}</Badge>
                                            ) : (
                                                <SegmentedControl
                                                    size="xs"
                                                    value={current ?? ''}
                                                    onChange={(val) => setDecision(row.question_answer_id, val as Decision)}
                                                    data={[
                                                        {value: 'leave_alone', label: t`Leave`},
                                                        {value: 'update', label: t`Update`},
                                                    ]}
                                                />
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
