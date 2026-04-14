import {t} from "@lingui/macro";
import {Alert, Badge, Group, SegmentedControl, Select, Table, Text, TextInput} from "@mantine/core";
import {Card} from "../../../common/Card";
import {useParams} from "react-router";
import {useGetTransactionMessages} from "../../../../queries/useGetTransactionMessages.ts";
import {relativeDate} from "../../../../utilites/dates.ts";
import {Pagination} from "../../../common/Pagination";
import {useState} from "react";
import {TableSkeleton} from "../../../common/TableSkeleton";
import {QueryFilterOperator} from "../../../../types.ts";
import {IconSearch} from "@tabler/icons-react";
import {statusColor, emailTypeLabel, statusFilterOptions, emailTypeFilterOptions, dateRangeOptions} from "./shared.tsx";

export const TransactionsTab = () => {
    const {eventId} = useParams();
    const [page, setPage] = useState(1);
    const [query, setQuery] = useState('');
    const [statusFilter, setStatusFilter] = useState<string | null>(null);
    const [emailTypeFilter, setEmailTypeFilter] = useState<string | null>(null);
    const [dateRange, setDateRange] = useState('all');

    const filterFields: Record<string, any> = {};
    if (statusFilter) {
        filterFields.status = {operator: QueryFilterOperator.In, value: statusFilter};
    }
    if (emailTypeFilter) {
        filterFields.email_type = {operator: QueryFilterOperator.In, value: emailTypeFilter};
    }
    if (dateRange !== 'all') {
        filterFields.date_range = {operator: QueryFilterOperator.Equals, value: dateRange};
    }

    const searchParams: any = {
        pageNumber: page,
        perPage: 20,
        query: query || undefined,
        filterFields: Object.keys(filterFields).length > 0 ? filterFields : undefined,
    };

    const messagesQuery = useGetTransactionMessages(eventId, searchParams);
    const messages = messagesQuery.data?.data;
    const pagination = messagesQuery.data?.meta;

    return (
        <>
            <div style={{marginBottom: 'var(--mantine-spacing-md)'}}><Card>
                <Group gap="sm" wrap="wrap" style={{alignItems: 'center'}}>
                    <Select
                        placeholder={t`Email Type`}
                        data={emailTypeFilterOptions}
                        value={emailTypeFilter}
                        onChange={(val) => { setEmailTypeFilter(val); setPage(1); }}
                        clearable
                        size="sm"
                        style={{width: 180}}
                    />
                    <Select
                        placeholder={t`Status`}
                        data={statusFilterOptions}
                        value={statusFilter}
                        onChange={(val) => { setStatusFilter(val); setPage(1); }}
                        clearable
                        size="sm"
                        style={{width: 150}}
                    />
                    <TextInput
                        placeholder={t`Search by recipient or subject...`}
                        leftSection={<IconSearch size={16}/>}
                        value={query}
                        onChange={(e) => { setQuery(e.currentTarget.value); setPage(1); }}
                        size="sm"
                        style={{flex: 1, minWidth: 200}}
                    />
                    <SegmentedControl
                        value={dateRange}
                        onChange={(val) => { setDateRange(val); setPage(1); }}
                        data={dateRangeOptions}
                        size="xs"
                        style={{marginBottom: 20}}
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
                            {t`No transaction messages found.`}
                        </Text>
                    )}

                    {messages && messages.length > 0 && (
                        <>
                            <Table striped highlightOnHover>
                                <Table.Thead>
                                    <Table.Tr>
                                        <Table.Th>{t`Email Type`}</Table.Th>
                                        <Table.Th>{t`Status`}</Table.Th>
                                        <Table.Th>{t`Subject`}</Table.Th>
                                        <Table.Th>{t`Recipient`}</Table.Th>
                                        <Table.Th>{t`Date`}</Table.Th>
                                    </Table.Tr>
                                </Table.Thead>
                                <Table.Tbody>
                                    {messages.map((msg) => (
                                        <Table.Tr key={String(msg.id)}>
                                            <Table.Td>{emailTypeLabel(msg.email_type)}</Table.Td>
                                            <Table.Td>
                                                <Badge size="sm" color={statusColor(msg.status)} variant="filled">
                                                    {msg.status}
                                                </Badge>
                                            </Table.Td>
                                            <Table.Td>{msg.subject}</Table.Td>
                                            <Table.Td>{msg.recipient}</Table.Td>
                                            <Table.Td>{relativeDate(msg.created_at)}</Table.Td>
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
        </>
    );
};
