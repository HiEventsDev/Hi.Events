import {Badge, Table, Text, Button, ActionIcon, Group, Tooltip, Stack} from "@mantine/core";
import {t} from "@lingui/macro";
import {AdminEvent} from "../../../api/admin.client";
import {IconChevronDown, IconChevronUp, IconEye, IconUserCheck} from "@tabler/icons-react";
import {IdParam} from "../../../types";
import classes from "./AdminEventsTable.module.scss";

interface AdminEventsTableProps {
    events: AdminEvent[];
    onSort?: (column: string) => void;
    sortBy?: string;
    sortDirection?: 'asc' | 'desc';
    onViewEvent?: (event: AdminEvent) => void;
    onImpersonate?: (userId: IdParam, accountId: IdParam) => void;
    isImpersonating?: boolean;
}

const AdminEventsTable = ({events, onSort, sortBy, sortDirection, onViewEvent, onImpersonate, isImpersonating}: AdminEventsTableProps) => {
    if (!events || events.length === 0) {
        return (
            <div className={classes.emptyState}>
                <Text size="lg" c="dimmed">{t`No events found`}</Text>
            </div>
        );
    }

    const formatDate = (dateString?: string | null) => {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleDateString();
    };

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(amount);
    };

    const formatNumber = (num: number) => {
        return new Intl.NumberFormat('en-US').format(num);
    };

    const getStatusBadgeColor = (status: string) => {
        switch (status.toUpperCase()) {
            case 'LIVE':
                return 'green';
            case 'DRAFT':
                return 'yellow';
            case 'ARCHIVED':
                return 'gray';
            default:
                return 'blue';
        }
    };

    const handleSort = (column: string) => {
        if (onSort) {
            onSort(column);
        }
    };

    const SortIcon = ({column}: {column: string}) => {
        if (sortBy !== column) return null;
        return sortDirection === 'asc' ? <IconChevronUp size={14} /> : <IconChevronDown size={14} />;
    };

    return (
        <div className={classes.tableContainer}>
            <Table striped highlightOnHover>
                <Table.Thead>
                    <Table.Tr>
                        <Table.Th>
                            <Button
                                variant="subtle"
                                size="compact-sm"
                                onClick={() => handleSort('title')}
                                rightSection={<SortIcon column="title" />}
                            >
                                {t`Event Title`}
                            </Button>
                        </Table.Th>
                        <Table.Th>{t`Organizer`}</Table.Th>
                        <Table.Th>
                            <Button
                                variant="subtle"
                                size="compact-sm"
                                onClick={() => handleSort('start_date')}
                                rightSection={<SortIcon column="start_date" />}
                            >
                                {t`Start Date`}
                            </Button>
                        </Table.Th>
                        <Table.Th>
                            <Button
                                variant="subtle"
                                size="compact-sm"
                                onClick={() => handleSort('end_date')}
                                rightSection={<SortIcon column="end_date" />}
                            >
                                {t`End Date`}
                            </Button>
                        </Table.Th>
                        <Table.Th>{t`Statistics`}</Table.Th>
                        <Table.Th>{t`Status`}</Table.Th>
                        <Table.Th>{t`Actions`}</Table.Th>
                    </Table.Tr>
                </Table.Thead>
                <Table.Tbody>
                    {events.map((event) => (
                        <Table.Tr key={event.id}>
                            <Table.Td>
                                <Text fw={500}>{event.title}</Text>
                            </Table.Td>
                            <Table.Td>
                                <Text size="sm">{event.organizer_name}</Text>
                            </Table.Td>
                            <Table.Td>
                                <Text size="sm">{formatDate(event.start_date)}</Text>
                            </Table.Td>
                            <Table.Td>
                                <Text size="sm">{formatDate(event.end_date)}</Text>
                            </Table.Td>
                            <Table.Td>
                                {event.statistics ? (
                                    <Stack gap={4}>
                                        <Group gap={6}>
                                            <Text size="xs" c="dimmed">{t`Sales:`}</Text>
                                            <Text size="xs" fw={600}>{formatCurrency(event.statistics.total_gross_sales)}</Text>
                                        </Group>
                                        <Group gap={6}>
                                            <Text size="xs" c="dimmed">{t`Attendees:`}</Text>
                                            <Text size="xs" fw={500}>{formatNumber(event.statistics.attendees_registered)}</Text>
                                        </Group>
                                        <Group gap={6}>
                                            <Text size="xs" c="dimmed">{t`Orders:`}</Text>
                                            <Text size="xs" fw={500}>{formatNumber(event.statistics.orders_created)}</Text>
                                        </Group>
                                    </Stack>
                                ) : (
                                    <Text size="sm" c="dimmed">-</Text>
                                )}
                            </Table.Td>
                            <Table.Td>
                                <Badge color={getStatusBadgeColor(event.status)}>
                                    {event.status}
                                </Badge>
                            </Table.Td>
                            <Table.Td>
                                <Group gap="xs">
                                    <Tooltip label={t`View Event`}>
                                        <ActionIcon
                                            variant="subtle"
                                            color="blue"
                                            onClick={() => onViewEvent?.(event)}
                                        >
                                            <IconEye size={18} />
                                        </ActionIcon>
                                    </Tooltip>
                                    <Tooltip label={t`Impersonate User`}>
                                        <ActionIcon
                                            variant="subtle"
                                            color="grape"
                                            onClick={() => onImpersonate?.(event.user_id, event.account_id)}
                                            disabled={isImpersonating}
                                        >
                                            <IconUserCheck size={18} />
                                        </ActionIcon>
                                    </Tooltip>
                                </Group>
                            </Table.Td>
                        </Table.Tr>
                    ))}
                </Table.Tbody>
            </Table>
        </div>
    );
};

export default AdminEventsTable;
