import {t} from "@lingui/macro";
import {useMemo, useState} from "react";
import {Alert, Button, Checkbox, Group, Select, Switch, Table, Text, TextInput} from "@mantine/core";
import {IconEyeOff, IconRotateClockwise, IconSearch, IconUserPlus} from "@tabler/icons-react";
import {Card} from "../../../../common/Card";
import {Pagination} from "../../../../common/Pagination";
import {SortableTh} from "../../../../common/SortableTh";
import {TableSkeleton} from "../../../../common/TableSkeleton";
import {QueryFilterOperator, QueryFilters} from "../../../../../types.ts";
import {useGetEvents} from "../../../../../queries/useGetEvents.ts";
import {useGetBackfillUnlinkedAttendees} from "../../../../../queries/useGetBackfillUnlinkedAttendees.ts";
import {useAddContactsFromAttendees} from "../../../../../mutations/useAddContactsFromAttendees.ts";
import {useIgnoreAttendees} from "../../../../../mutations/useIgnoreAttendees.ts";
import {useUnignoreAttendees} from "../../../../../mutations/useUnignoreAttendees.ts";
import {useRowSelection} from "../../../../../hooks/useRowSelection.ts";
import {showError, showSuccess} from "../../../../../utilites/notifications.tsx";

export const UnlinkedAttendeesSubTab = () => {
    const [page, setPage] = useState(1);
    const [query, setQuery] = useState('');
    const [eventFilter, setEventFilter] = useState<string | null>(null);
    const [sortBy, setSortBy] = useState('email');
    const [sortDir, setSortDir] = useState('asc');
    const [includeIgnored, setIncludeIgnored] = useState(false);

    const eventsQuery = useGetEvents({pageNumber: 1, perPage: 100});
    const eventOptions = useMemo(
        () => (eventsQuery.data?.data ?? []).map((e: any) => ({value: String(e.id), label: e.title})),
        [eventsQuery.data],
    );

    const addMutation = useAddContactsFromAttendees();
    const ignoreMutation = useIgnoreAttendees();
    const unignoreMutation = useUnignoreAttendees();

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

    const result = useGetBackfillUnlinkedAttendees(params, includeIgnored);
    const rows = result.data?.data;
    const meta = result.data?.meta;

    const idsInOrder = useMemo(() => (rows ?? []).map((r) => r.id), [rows]);
    const selection = useRowSelection<number>(idsInOrder);

    const selectedActive = useMemo(
        () => (rows ?? []).filter((r) => selection.isSelected(r.id) && !r.contact_link_ignored_at),
        [rows, selection],
    );
    const selectedIgnored = useMemo(
        () => (rows ?? []).filter((r) => selection.isSelected(r.id) && !!r.contact_link_ignored_at),
        [rows, selection],
    );

    const activeRows = useMemo(() => (rows ?? []).filter((r) => !r.contact_link_ignored_at), [rows]);
    const allActiveSelected = activeRows.length > 0 && activeRows.every((r) => selection.isSelected(r.id));

    const handleAdd = () => {
        const ids = selectedActive.map((r) => r.id);
        if (ids.length === 0) return;
        addMutation.mutate({attendeeIds: ids}, {
            onSuccess: (res) => {
                showSuccess(t`Added ${res.data.count} contact(s).`);
                selection.clear();
            },
            onError: () => showError(t`Could not add contacts.`),
        });
    };

    const handleIgnore = () => {
        const ids = selectedActive.map((r) => r.id);
        if (ids.length === 0) return;
        ignoreMutation.mutate({attendeeIds: ids}, {
            onSuccess: () => {
                showSuccess(t`Ignored ${ids.length} attendee(s).`);
                selection.clear();
            },
            onError: () => showError(t`Could not ignore attendees.`),
        });
    };

    const handleUnignore = () => {
        const ids = selectedIgnored.map((r) => r.id);
        if (ids.length === 0) return;
        unignoreMutation.mutate({attendeeIds: ids}, {
            onSuccess: () => {
                showSuccess(t`Restored ${ids.length} attendee(s).`);
                selection.clear();
            },
            onError: () => showError(t`Could not restore attendees.`),
        });
    };

    const handleSelectAll = () => {
        if (selection.count > 0) selection.clear();
        else selection.selectAll();
    };

    return (
        <Card>
            <Group gap="sm" wrap="wrap" mb="md" align="center">
                <TextInput
                    placeholder={t`Search by name or email...`}
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
                    label={t`Show ignored`}
                    checked={includeIgnored}
                    onChange={(e) => { setIncludeIgnored(e.currentTarget.checked); setPage(1); }}
                    size="sm"
                />
                <Button
                    size="sm"
                    leftSection={<IconUserPlus size={14}/>}
                    disabled={selectedActive.length === 0}
                    loading={addMutation.isPending}
                    onClick={handleAdd}
                >
                    {selectedActive.length > 0 ? t`Add (${selectedActive.length})` : t`Add`}
                </Button>
                <Button
                    size="sm"
                    variant="default"
                    leftSection={<IconEyeOff size={14}/>}
                    disabled={selectedActive.length === 0}
                    loading={ignoreMutation.isPending}
                    onClick={handleIgnore}
                >
                    {t`Ignore`}
                </Button>
                {includeIgnored && selectedIgnored.length > 0 && (
                    <Button
                        size="sm"
                        variant="light"
                        leftSection={<IconRotateClockwise size={14}/>}
                        loading={unignoreMutation.isPending}
                        onClick={handleUnignore}
                    >
                        {t`Unignore (${selectedIgnored.length})`}
                    </Button>
                )}
            </Group>

            {result.isLoading && <TableSkeleton isVisible/>}

            {!!result.error && (
                <Alert color="red" radius="md">{t`Failed to load unlinked attendees`}</Alert>
            )}

            {!result.isLoading && !result.error && rows && rows.length === 0 && (
                <Text c="dimmed" ta="center" py="xl">
                    {query || eventFilter ? t`No matching attendees.` : t`No unlinked attendees. All attendees already have a contact.`}
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
                                        onChange={handleSelectAll}
                                    />
                                </Table.Th>
                                <SortableTh label={t`Email`} field="email" sortBy={sortBy} sortDir={sortDir} onSort={handleSort}/>
                                <SortableTh label={t`First Name`} field="first_name" sortBy={sortBy} sortDir={sortDir} onSort={handleSort}/>
                                <SortableTh label={t`Last Name`} field="last_name" sortBy={sortBy} sortDir={sortDir} onSort={handleSort}/>
                                <SortableTh label={t`Event`} field="event_title" sortBy={sortBy} sortDir={sortDir} onSort={handleSort}/>
                                <SortableTh label={t`Created`} field="created_at" sortBy={sortBy} sortDir={sortDir} onSort={handleSort}/>
                            </Table.Tr>
                        </Table.Thead>
                        <Table.Tbody>
                            {rows.map((row) => {
                                const isIgnored = !!row.contact_link_ignored_at;
                                const rowStyle = isIgnored ? {opacity: 0.55} : undefined;
                                return (
                                    <Table.Tr key={row.id} style={rowStyle}>
                                        <Table.Td>
                                            <Checkbox
                                                aria-label={t`Select row`}
                                                checked={selection.isSelected(row.id)}
                                                onMouseDown={(e) => selection.captureShift(e.shiftKey)}
                                                onKeyDown={(e) => selection.captureShift(e.shiftKey)}
                                                onChange={() => selection.toggleWithStoredShift(row.id)}
                                            />
                                        </Table.Td>
                                        <Table.Td>
                                            {row.email}
                                            {isIgnored && (
                                                <Text component="span" size="xs" c="dimmed" ml={6}>({t`ignored`})</Text>
                                            )}
                                        </Table.Td>
                                        <Table.Td>{row.first_name || '-'}</Table.Td>
                                        <Table.Td>{row.last_name || '-'}</Table.Td>
                                        <Table.Td>{row.event_title}</Table.Td>
                                        <Table.Td>{row.created_at ? new Date(row.created_at).toLocaleDateString() : '-'}</Table.Td>
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
