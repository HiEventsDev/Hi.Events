import {Container, Title, Stack, Card, Text, Group, Button, Badge, Skeleton, Table} from "@mantine/core";
import {t} from "@lingui/macro";
import {useParams, useNavigate} from "react-router";
import {useGetAdminOrder} from "../../../../../queries/useGetAdminOrder";
import {IconArrowLeft} from "@tabler/icons-react";
import {formatCurrency} from "../../../../../utilites/currency";
import {prettyDate} from "../../../../../utilites/dates";
import classes from "./OrderDetail.module.scss";

const getStatusBadgeColor = (status: string) => {
    switch (status?.toUpperCase()) {
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

const getAttendeeStatusColor = (status: string) => {
    switch (status?.toUpperCase()) {
        case 'ACTIVE':
            return 'green';
        case 'CANCELLED':
            return 'red';
        case 'AWAITING_PAYMENT':
            return 'yellow';
        default:
            return 'gray';
    }
};

const OrderDetail = () => {
    const {orderId} = useParams();
    const navigate = useNavigate();
    const {data: orderData, isLoading} = useGetAdminOrder(orderId);

    if (isLoading) {
        return (
            <Container size="xl" p="xl">
                <Stack gap="lg">
                    <Skeleton height={40} width={200} />
                    <Skeleton height={200} radius="md" />
                    <Skeleton height={150} radius="md" />
                </Stack>
            </Container>
        );
    }

    const order = orderData?.data;

    if (!order) {
        return (
            <Container size="xl" p="xl">
                <Text c="dimmed">{t`Order not found`}</Text>
            </Container>
        );
    }

    return (
        <Container size="xl" p="xl">
            <Stack gap="lg">
                <Group>
                    <Button
                        variant="subtle"
                        leftSection={<IconArrowLeft size={16} />}
                        onClick={() => navigate('/admin/orders')}
                    >
                        {t`Back to Orders`}
                    </Button>
                </Group>

                <Group justify="space-between" align="center">
                    <Title order={1}>{t`Order`} #{order.short_id}</Title>
                    <Badge color={getStatusBadgeColor(order.status)} size="lg">
                        {order.status}
                    </Badge>
                </Group>

                <Card className={classes.card}>
                    <Stack gap="md">
                        <Text size="lg" fw={600}>{t`Customer Information`}</Text>
                        <div className={classes.infoGrid}>
                            <div className={classes.infoItem}>
                                <Text size="xs" c="dimmed">{t`Name`}</Text>
                                <Text size="sm" fw={500}>{order.first_name} {order.last_name}</Text>
                            </div>
                            <div className={classes.infoItem}>
                                <Text size="xs" c="dimmed">{t`Email`}</Text>
                                <Text size="sm">{order.email}</Text>
                            </div>
                            <div className={classes.infoItem}>
                                <Text size="xs" c="dimmed">{t`Event`}</Text>
                                <Text size="sm">{order.event_title || '-'}</Text>
                            </div>
                            <div className={classes.infoItem}>
                                <Text size="xs" c="dimmed">{t`Account`}</Text>
                                <Text size="sm">{order.account_name || '-'}</Text>
                            </div>
                        </div>
                    </Stack>
                </Card>

                <Card className={classes.card}>
                    <Stack gap="md">
                        <Text size="lg" fw={600}>{t`Payment Details`}</Text>
                        <div className={classes.infoGrid}>
                            <div className={classes.infoItem}>
                                <Text size="xs" c="dimmed">{t`Subtotal`}</Text>
                                <Text size="sm" fw={500}>{formatCurrency(order.total_before_additions, order.currency)}</Text>
                            </div>
                            <div className={classes.infoItem}>
                                <Text size="xs" c="dimmed">{t`Tax`}</Text>
                                <Text size="sm">{formatCurrency(order.total_tax, order.currency)}</Text>
                            </div>
                            <div className={classes.infoItem}>
                                <Text size="xs" c="dimmed">{t`Fees`}</Text>
                                <Text size="sm">{formatCurrency(order.total_fee, order.currency)}</Text>
                            </div>
                            <div className={classes.infoItem}>
                                <Text size="xs" c="dimmed">{t`Total`}</Text>
                                <Text size="sm" fw={600}>{formatCurrency(order.total_gross, order.currency)}</Text>
                            </div>
                            {order.total_refunded > 0 && (
                                <div className={classes.infoItem}>
                                    <Text size="xs" c="dimmed">{t`Refunded`}</Text>
                                    <Text size="sm" c="red" fw={500}>{formatCurrency(order.total_refunded, order.currency)}</Text>
                                </div>
                            )}
                            <div className={classes.infoItem}>
                                <Text size="xs" c="dimmed">{t`Payment Status`}</Text>
                                <Badge variant="light" size="sm">{order.payment_status || '-'}</Badge>
                            </div>
                            {order.payment_gateway && (
                                <div className={classes.infoItem}>
                                    <Text size="xs" c="dimmed">{t`Payment Gateway`}</Text>
                                    <Text size="sm">{order.payment_gateway}</Text>
                                </div>
                            )}
                            {order.promo_code && (
                                <div className={classes.infoItem}>
                                    <Text size="xs" c="dimmed">{t`Promo Code`}</Text>
                                    <Badge variant="light" color="violet" size="sm">{order.promo_code}</Badge>
                                </div>
                            )}
                        </div>
                    </Stack>
                </Card>

                <Card className={classes.card}>
                    <Stack gap="md">
                        <Text size="lg" fw={600}>{t`Order Details`}</Text>
                        <div className={classes.infoGrid}>
                            <div className={classes.infoItem}>
                                <Text size="xs" c="dimmed">{t`Order ID`}</Text>
                                <Text size="sm">#{order.short_id}</Text>
                            </div>
                            <div className={classes.infoItem}>
                                <Text size="xs" c="dimmed">{t`Public ID`}</Text>
                                <Text size="sm">{order.public_id}</Text>
                            </div>
                            <div className={classes.infoItem}>
                                <Text size="xs" c="dimmed">{t`Created`}</Text>
                                <Text size="sm">{prettyDate(order.created_at, 'UTC')}</Text>
                            </div>
                            {order.notes && (
                                <div className={classes.infoItem}>
                                    <Text size="xs" c="dimmed">{t`Notes`}</Text>
                                    <Text size="sm">{order.notes}</Text>
                                </div>
                            )}
                        </div>
                    </Stack>
                </Card>

                {order.attendees && order.attendees.length > 0 && (
                    <Card className={classes.card}>
                        <Stack gap="md">
                            <Text size="lg" fw={600}>{t`Attendees`} ({order.attendees.length})</Text>
                            <Table highlightOnHover>
                                <Table.Thead>
                                    <Table.Tr>
                                        <Table.Th>{t`Name`}</Table.Th>
                                        <Table.Th>{t`Email`}</Table.Th>
                                        <Table.Th>{t`Status`}</Table.Th>
                                        <Table.Th>{t`Ticket ID`}</Table.Th>
                                        <Table.Th>{t`Checked In`}</Table.Th>
                                    </Table.Tr>
                                </Table.Thead>
                                <Table.Tbody>
                                    {order.attendees.map((attendee) => (
                                        <Table.Tr key={attendee.id}>
                                            <Table.Td>
                                                <Text size="sm" fw={500}>{attendee.first_name} {attendee.last_name}</Text>
                                            </Table.Td>
                                            <Table.Td>
                                                <Text size="sm">{attendee.email}</Text>
                                            </Table.Td>
                                            <Table.Td>
                                                <Badge color={getAttendeeStatusColor(attendee.status)} size="sm" variant="light">
                                                    {attendee.status}
                                                </Badge>
                                            </Table.Td>
                                            <Table.Td>
                                                <Text size="sm" c="dimmed">{attendee.short_id}</Text>
                                            </Table.Td>
                                            <Table.Td>
                                                <Text size="sm">
                                                    {attendee.checked_in_at ? prettyDate(attendee.checked_in_at, 'UTC') : '-'}
                                                </Text>
                                            </Table.Td>
                                        </Table.Tr>
                                    ))}
                                </Table.Tbody>
                            </Table>
                        </Stack>
                    </Card>
                )}
            </Stack>
        </Container>
    );
};

export default OrderDetail;
