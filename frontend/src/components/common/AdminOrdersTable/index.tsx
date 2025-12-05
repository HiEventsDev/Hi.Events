import {Badge, Table, Text, Button, Group} from "@mantine/core";
import {t} from "@lingui/macro";
import {AdminOrder} from "../../../api/admin.client";
import {IconChevronDown, IconChevronUp} from "@tabler/icons-react";
import {formatCurrency} from "../../../utilites/currency";
import {prettyDate} from "../../../utilites/dates";
import classes from "./AdminOrdersTable.module.scss";

interface AdminOrdersTableProps {
    orders: AdminOrder[];
    onSort?: (column: string) => void;
    sortBy?: string;
    sortDirection?: 'asc' | 'desc';
}

const AdminOrdersTable = ({orders, onSort, sortBy, sortDirection}: AdminOrdersTableProps) => {
    if (!orders || orders.length === 0) {
        return (
            <div className={classes.emptyState}>
                <Text size="lg" c="dimmed">{t`No orders found`}</Text>
            </div>
        );
    }

    const handleSort = (column: string) => {
        if (onSort) {
            onSort(column);
        }
    };

    const SortIcon = ({column}: {column: string}) => {
        if (sortBy !== column) return null;
        return sortDirection === 'asc' ? <IconChevronUp size={14} /> : <IconChevronDown size={14} />;
    };

    const getStatusBadgeColor = (status: string) => {
        switch (status.toUpperCase()) {
            case 'COMPLETED':
                return 'green';
            case 'PENDING':
            case 'RESERVED':
                return 'yellow';
            case 'CANCELLED':
                return 'red';
            case 'REFUNDED':
            case 'PARTIALLY_REFUNDED':
                return 'blue';
            default:
                return 'gray';
        }
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
                                onClick={() => handleSort('short_id')}
                                rightSection={<SortIcon column="short_id" />}
                            >
                                {t`Order ID`}
                            </Button>
                        </Table.Th>
                        <Table.Th>{t`Account`}</Table.Th>
                        <Table.Th>{t`Customer`}</Table.Th>
                        <Table.Th>{t`Event`}</Table.Th>
                        <Table.Th>
                            <Button
                                variant="subtle"
                                size="compact-sm"
                                onClick={() => handleSort('total_gross')}
                                rightSection={<SortIcon column="total_gross" />}
                            >
                                {t`Total`}
                            </Button>
                        </Table.Th>
                        <Table.Th>{t`Tax`}</Table.Th>
                        <Table.Th>{t`Status`}</Table.Th>
                        <Table.Th>
                            <Button
                                variant="subtle"
                                size="compact-sm"
                                onClick={() => handleSort('created_at')}
                                rightSection={<SortIcon column="created_at" />}
                            >
                                {t`Date`}
                            </Button>
                        </Table.Th>
                    </Table.Tr>
                </Table.Thead>
                <Table.Tbody>
                    {orders.map((order) => (
                        <Table.Tr key={order.id}>
                            <Table.Td>
                                <Text fw={500} size="sm">#{order.short_id}</Text>
                            </Table.Td>
                            <Table.Td>
                                <Text size="sm">{order.account_name}</Text>
                            </Table.Td>
                            <Table.Td>
                                <div>
                                    <Text size="sm" fw={500}>{order.first_name} {order.last_name}</Text>
                                    <Text size="xs" c="dimmed">{order.email}</Text>
                                </div>
                            </Table.Td>
                            <Table.Td>
                                <Text size="sm">{order.event_title}</Text>
                            </Table.Td>
                            <Table.Td>
                                <Text size="sm" fw={600}>{formatCurrency(order.total_gross, order.currency)}</Text>
                            </Table.Td>
                            <Table.Td>
                                <Text size="sm">{formatCurrency(order.total_tax, order.currency)}</Text>
                            </Table.Td>
                            <Table.Td>
                                <Group gap="xs">
                                    <Badge color={getStatusBadgeColor(order.status)} size="sm">
                                        {order.status}
                                    </Badge>
                                </Group>
                            </Table.Td>
                            <Table.Td>
                                <Text size="sm">{prettyDate(order.created_at, 'UTC')}</Text>
                            </Table.Td>
                        </Table.Tr>
                    ))}
                </Table.Tbody>
            </Table>
        </div>
    );
};

export default AdminOrdersTable;
