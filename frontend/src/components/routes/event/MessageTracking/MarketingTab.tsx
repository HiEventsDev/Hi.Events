import {t} from "@lingui/macro";
import {ActionIcon, Alert, Badge, Button, Group, SegmentedControl, Select, Table, Text, TextInput, Tooltip, UnstyledButton} from "@mantine/core";
import {Card} from "../../../common/Card";
import {useParams} from "react-router";
import {useGetOutgoingMessages, GET_OUTGOING_MESSAGES_QUERY_KEY} from "../../../../queries/useGetOutgoingMessages.ts";
import {relativeDate} from "../../../../utilites/dates.ts";
import {Pagination} from "../../../common/Pagination";
import {useState} from "react";
import {TableSkeleton} from "../../../common/TableSkeleton";
import {DeliveryIssue, OutgoingMessage, QueryFilterOperator} from "../../../../types.ts";
import {IconCheck, IconCircleDashed, IconSearch, IconSortAscending, IconSortDescending, IconUserCheck} from "@tabler/icons-react";
import {statusColor, statusFilterOptions, dateRangeOptions, ResolvedHoverCard, RetryHoverCard} from "./shared.tsx";
import {useResolveDeliveryIssue} from "../../../../mutations/useResolveDeliveryIssue.ts";
import {ResolveDeliveryIssueModal} from "../../../modals/ResolveDeliveryIssueModal";
import {showError} from "../../../../utilites/notifications.tsx";
import {useQueryClient} from "@tanstack/react-query";

const FAILED_STATUSES = ['BOUNCED', 'FAILED', 'SUPPRESSED'];

const toDeliveryIssue = (msg: OutgoingMessage): DeliveryIssue => ({
    id: msg.id!,
    source_type: 'announcement',
    email_type: 'announcement',
    status: msg.status,
    subject: msg.subject,
    recipient: msg.recipient,
    updated_at: msg.created_at || '',
    resolved_at: msg.resolved_at || null,
    retry_for_id: msg.retry_for_id || null,
});

interface SortableThProps {
    label: string;
    field: string;
    sortBy: string;
    sortDir: string;
    onSort: (field: string) => void;
}

const SortableTh = ({label, field, sortBy, sortDir, onSort}: SortableThProps) => {
    const isActive = sortBy === field;
    const Icon = isActive && sortDir === 'asc' ? IconSortAscending : IconSortDescending;

    return (
        <Table.Th>
            <UnstyledButton onClick={() => onSort(field)} style={{display: 'flex', alignItems: 'center', gap: 4, fontWeight: 700}}>
                {label}
                {isActive && <Icon size={14} style={{opacity: 0.6}}/>}
            </UnstyledButton>
        </Table.Th>
    );
};

export const MarketingTab = () => {
    const {eventId} = useParams();
    const [page, setPage] = useState(1);
    const [query, setQuery] = useState('');
    const [statusFilter, setStatusFilter] = useState<string | null>(null);
    const [dateRange, setDateRange] = useState('30d');
    const [sortBy, setSortBy] = useState('created_at');
    const [sortDir, setSortDir] = useState('desc');
    const [resolveModalMessage, setResolveModalMessage] = useState<DeliveryIssue | null>(null);
    const [resolvingId, setResolvingId] = useState<number | string | null>(null);
    const resolveMutation = useResolveDeliveryIssue();
    const queryClient = useQueryClient();

    const isIssuesFilter = dateRange === 'issues';

    const handleSort = (field: string) => {
        if (sortBy === field) {
            setSortDir(sortDir === 'asc' ? 'desc' : 'asc');
        } else {
            setSortBy(field);
            setSortDir('asc');
        }
        setPage(1);
    };

    const filterFields: Record<string, any> = {};
    if (isIssuesFilter) {
        filterFields.status = {operator: QueryFilterOperator.In, value: FAILED_STATUSES.join(',')};
    } else {
        if (statusFilter) {
            filterFields.status = {operator: QueryFilterOperator.In, value: statusFilter};
        }
        if (dateRange !== 'all') {
            filterFields.date_range = {operator: QueryFilterOperator.Equals, value: dateRange};
        }
    }

    const searchParams: any = {
        pageNumber: page,
        perPage: 20,
        query: query || undefined,
        filterFields: Object.keys(filterFields).length > 0 ? filterFields : undefined,
        sortBy,
        sortDirection: sortDir,
    };

    const messagesQuery = useGetOutgoingMessages(eventId, searchParams);
    const messages = messagesQuery.data?.data;
    const pagination = messagesQuery.data?.meta;

    const handleToggleResolved = (msg: OutgoingMessage) => {
        setResolvingId(msg.id!);
        resolveMutation.mutate({
            eventId: eventId!,
            messageId: msg.id!,
            sourceType: 'announcement',
        }, {
            onSuccess: () => {
                queryClient.invalidateQueries({queryKey: [GET_OUTGOING_MESSAGES_QUERY_KEY]});
                setResolvingId(null);
            },
            onError: () => {
                showError(t`Failed to update resolved status.`);
                setResolvingId(null);
            },
        });
    };

    const isFailed = (status: string) => FAILED_STATUSES.includes(status.toUpperCase());

    const getResolvedIcon = (msg: OutgoingMessage) => {
        if (!isFailed(msg.status)) return null;

        if (!msg.resolved_at) {
            return (
                <Tooltip label={t`Mark as resolved`}>
                    <ActionIcon variant="subtle" size="sm" color="gray" onClick={() => handleToggleResolved(msg)} loading={resolvingId === msg.id}>
                        <IconCircleDashed size={16}/>
                    </ActionIcon>
                </Tooltip>
            );
        }

        const isAuto = msg.resolution_type === 'auto';
        const isManual = msg.resolution_type === 'manual';
        const icon = (
            <ActionIcon
                variant="subtle"
                size="sm"
                color={isManual ? 'blue' : 'green'}
                style={isAuto ? {cursor: 'default'} : undefined}
                onClick={isAuto ? undefined : () => handleToggleResolved(msg)}
                loading={resolvingId === msg.id}
            >
                {isManual ? <IconUserCheck size={16}/> : <IconCheck size={16}/>}
            </ActionIcon>
        );

        return <ResolvedHoverCard msg={msg}><span>{icon}</span></ResolvedHoverCard>;
    };

    const getStatusBadge = (msg: OutgoingMessage) => {
        if (msg.resolved_at) {
            return (
                <ResolvedHoverCard msg={msg}>
                    <Badge size="sm" color="cyan" variant="filled" style={{cursor: 'default'}}>
                        {t`RESOLVED`}
                    </Badge>
                </ResolvedHoverCard>
            );
        }
        const badge = (
            <Badge size="sm" color={statusColor(msg.status)} variant="filled" style={msg.retry_for_id ? {cursor: 'default'} : undefined}>
                {msg.status}
            </Badge>
        );
        if (msg.retry_for_id) {
            return <RetryHoverCard msg={msg}><span>{badge}</span></RetryHoverCard>;
        }
        return badge;
    };

    const getActionButton = (msg: OutgoingMessage) => {
        if (!isFailed(msg.status)) return null;
        if (msg.retry_for_id) return null;
        if (msg.resolved_at) return null;

        const label = (msg.retry_count ?? 0) > 0 ? t`Retry` : t`Resolve`;

        return (
            <Button size="compact-xs" variant="light" onClick={() => setResolveModalMessage(toDeliveryIssue(msg))}>
                {label}
            </Button>
        );
    };

    return (
        <>
            <div style={{marginBottom: 'var(--mantine-spacing-md)'}}><Card>
                <Group gap="sm" wrap="wrap" style={{alignItems: 'center'}}>
                    <Select
                        placeholder={t`Status`}
                        data={statusFilterOptions}
                        value={isIssuesFilter ? null : statusFilter}
                        onChange={(val) => { setStatusFilter(val); setPage(1); }}
                        clearable
                        disabled={isIssuesFilter}
                        size="sm"
                        style={{width: 150, marginBottom: 0}}
                    />
                    <TextInput
                        placeholder={t`Search by recipient or subject...`}
                        leftSection={<IconSearch size={16}/>}
                        value={query}
                        onChange={(e) => { setQuery(e.currentTarget.value); setPage(1); }}
                        size="sm"
                        style={{flex: 1, minWidth: 200, marginBottom: 0}}
                    />
                    <SegmentedControl
                        value={isIssuesFilter ? '' : dateRange}
                        onChange={(val) => { setDateRange(val); setPage(1); }}
                        data={dateRangeOptions}
                        size="xs"
                    />
                    <SegmentedControl
                        value={isIssuesFilter ? 'issues' : ''}
                        onChange={() => { setDateRange(dateRange === 'issues' ? '30d' : 'issues'); setPage(1); }}
                        data={[{label: t`Issues`, value: 'issues'}]}
                        size="xs"
                    />
                </Group>
            </Card></div>

            {messagesQuery.isLoading && (
                <Card>
                    <TableSkeleton isVisible/>
                </Card>
            )}

            {!!messagesQuery.error && (
                <Alert color="red" radius="md">
                    {t`Failed to load messages`}
                </Alert>
            )}

            {!messagesQuery.isLoading && !messagesQuery.error && (
                <Card>
                    {messages && messages.length === 0 && (
                        <Text c="dimmed" ta="center" py="xl">
                            {isIssuesFilter
                                ? t`No delivery issues found.`
                                : t`No announcement messages found.`
                            }
                        </Text>
                    )}

                    {messages && messages.length > 0 && (
                        <>
                            <Table striped highlightOnHover>
                                <Table.Thead>
                                    <Table.Tr>
                                        <Table.Th style={{width: 40}}></Table.Th>
                                        <SortableTh label={t`Status`} field="status" sortBy={sortBy} sortDir={sortDir} onSort={handleSort}/>
                                        <SortableTh label={t`Subject`} field="subject" sortBy={sortBy} sortDir={sortDir} onSort={handleSort}/>
                                        <SortableTh label={t`Recipient`} field="recipient" sortBy={sortBy} sortDir={sortDir} onSort={handleSort}/>
                                        <SortableTh label={t`Created`} field="created_at" sortBy={sortBy} sortDir={sortDir} onSort={handleSort}/>
                                        <SortableTh label={t`Updated`} field="updated_at" sortBy={sortBy} sortDir={sortDir} onSort={handleSort}/>
                                        <Table.Th></Table.Th>
                                    </Table.Tr>
                                </Table.Thead>
                                <Table.Tbody>
                                    {messages.map((msg) => (
                                        <Table.Tr key={String(msg.id)}>
                                            <Table.Td>{getResolvedIcon(msg)}</Table.Td>
                                            <Table.Td>{getStatusBadge(msg)}</Table.Td>
                                            <Table.Td>
                                                {msg.subject}
                                                {msg.retry_for_id && (
                                                    <RetryHoverCard msg={msg}>
                                                        <Text component="span" inherit style={{cursor: 'default'}}> · {t`Retry`}</Text>
                                                    </RetryHoverCard>
                                                )}
                                            </Table.Td>
                                            <Table.Td>{msg.recipient}</Table.Td>
                                            <Table.Td>{relativeDate(msg.created_at || '')}</Table.Td>
                                            <Table.Td>{msg.updated_at ? relativeDate(msg.updated_at) : ''}</Table.Td>
                                            <Table.Td>{getActionButton(msg)}</Table.Td>
                                        </Table.Tr>
                                    ))}
                                </Table.Tbody>
                            </Table>

                            {pagination && Number(pagination.last_page) > 1 && (
                                <Pagination
                                    value={page}
                                    onChange={setPage}
                                    total={Number(pagination.last_page)}
                                    size="sm"
                                />
                            )}
                        </>
                    )}
                </Card>
            )}

            {resolveModalMessage && (
                <ResolveDeliveryIssueModal
                    onClose={() => setResolveModalMessage(null)}
                    eventId={eventId!}
                    message={resolveModalMessage}
                />
            )}
        </>
    );
};
