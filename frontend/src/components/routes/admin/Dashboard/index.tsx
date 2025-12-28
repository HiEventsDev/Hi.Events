import {Container, Title, Text, Paper, Stack, Group, SimpleGrid, Skeleton, Badge, Anchor, Table} from "@mantine/core";
import {t, Trans} from "@lingui/macro";
import {
    IconUsers,
    IconBuildingBank,
    IconCalendarEvent,
    IconTicket,
    IconClock,
    IconTrendingUp,
    IconEye,
    IconCurrencyDollar,
    IconShoppingCart,
    IconUserPlus
} from "@tabler/icons-react";
import {useGetMe} from "../../../../queries/useGetMe";
import {useGetAdminStats} from "../../../../queries/useGetAdminStats";
import {useGetUpcomingEvents} from "../../../../queries/useGetUpcomingEvents";
import {useGetAdminDashboardData} from "../../../../queries/useGetAdminDashboardData";
import {eventHomepageUrl} from "../../../../utilites/urlHelper";
import dayjs from "dayjs";
import utc from 'dayjs/plugin/utc';
import timezone from 'dayjs/plugin/timezone';
import relativeTime from 'dayjs/plugin/relativeTime';

dayjs.extend(utc);
dayjs.extend(timezone);
dayjs.extend(relativeTime);

const AdminDashboard = () => {
    const {data: user} = useGetMe();
    const {data: stats, isLoading} = useGetAdminStats();
    const {data: upcomingEvents, isLoading: isLoadingEvents} = useGetUpcomingEvents(10);
    const {data: dashboardData, isLoading: isLoadingDashboard} = useGetAdminDashboardData({days: 14, limit: 10});

    const formatEventDate = (dateString: string, eventTimezone?: string) => {
        const eventDate = dayjs.utc(dateString);
        const now = dayjs();
        const diffMinutes = eventDate.diff(now, 'minute');
        const diffHours = eventDate.diff(now, 'hour');

        if (diffMinutes < 60) {
            return t`In ${diffMinutes} minutes`;
        } else if (diffHours < 24) {
            return t`In ${diffHours} hours`;
        }

        return eventTimezone
            ? eventDate.tz(eventTimezone).format('MMM D, h:mma')
            : eventDate.format('MMM D, h:mma');
    };

    const formatCurrency = (amount: number, currency?: string) => {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: currency || 'USD',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(amount);
    };

    const formatNumber = (num: number) => {
        return new Intl.NumberFormat().format(num);
    };

    return (
        <Container size="xl" p="xl">
            <Stack gap="xl">
                <div>
                    <Title order={1} mb="xs">
                        <Trans>Admin Dashboard</Trans>
                    </Title>
                    {user && (
                        <Text size="lg" c="dimmed">
                            <Trans>Hello {user.full_name}, manage your platform from here.</Trans>
                        </Text>
                    )}
                </div>

                {/* Main Stats */}
                <SimpleGrid cols={{base: 1, sm: 2, md: 4}} spacing="md">
                    <Paper shadow="sm" p="md" radius="md" withBorder>
                        <Group gap="xs">
                            <IconUsers size={32} color="var(--mantine-color-blue-6)" />
                            <div style={{flex: 1}}>
                                <Text size="xs" c="dimmed" fw={500}>
                                    {t`Total Users`}
                                </Text>
                                {isLoading ? (
                                    <Skeleton height={28} width={60} mt={4} />
                                ) : (
                                    <Text size="xl" fw={700}>
                                        {stats?.total_users?.toLocaleString() || 0}
                                    </Text>
                                )}
                            </div>
                        </Group>
                    </Paper>

                    <Paper shadow="sm" p="md" radius="md" withBorder>
                        <Group gap="xs">
                            <IconBuildingBank size={32} color="var(--mantine-color-green-6)" />
                            <div style={{flex: 1}}>
                                <Text size="xs" c="dimmed" fw={500}>
                                    {t`Total Accounts`}
                                </Text>
                                {isLoading ? (
                                    <Skeleton height={28} width={60} mt={4} />
                                ) : (
                                    <Text size="xl" fw={700}>
                                        {stats?.total_accounts?.toLocaleString() || 0}
                                    </Text>
                                )}
                            </div>
                        </Group>
                    </Paper>

                    <Paper shadow="sm" p="md" radius="md" withBorder>
                        <Group gap="xs">
                            <IconCalendarEvent size={32} color="var(--mantine-color-orange-6)" />
                            <div style={{flex: 1}}>
                                <Text size="xs" c="dimmed" fw={500}>
                                    {t`Live Events`}
                                </Text>
                                {isLoading ? (
                                    <Skeleton height={28} width={60} mt={4} />
                                ) : (
                                    <Text size="xl" fw={700}>
                                        {stats?.total_live_events?.toLocaleString() || 0}
                                    </Text>
                                )}
                            </div>
                        </Group>
                    </Paper>

                    <Paper shadow="sm" p="md" radius="md" withBorder>
                        <Group gap="xs">
                            <IconTicket size={32} color="var(--mantine-color-violet-6)" />
                            <div style={{flex: 1}}>
                                <Text size="xs" c="dimmed" fw={500}>
                                    {t`Tickets Sold`}
                                </Text>
                                {isLoading ? (
                                    <Skeleton height={28} width={60} mt={4} />
                                ) : (
                                    <Text size="xl" fw={700}>
                                        {stats?.total_tickets_sold?.toLocaleString() || 0}
                                    </Text>
                                )}
                            </div>
                        </Group>
                    </Paper>
                </SimpleGrid>

                {/* Recent Activity Stats (Last 14 Days) */}
                <div>
                    <Title order={2} mb="xs">
                        <Group gap="xs">
                            <IconTrendingUp size={24} />
                            <Trans>Last 14 Days</Trans>
                        </Group>
                    </Title>
                    <Text size="xs" c="dimmed" mb="md">
                        <Trans>Monetary values are approximate totals across all currencies</Trans>
                    </Text>
                    <SimpleGrid cols={{base: 1, sm: 2, md: 4}} spacing="md">
                        <Paper shadow="sm" p="md" radius="md" withBorder>
                            <Group gap="xs">
                                <IconCurrencyDollar size={32} color="var(--mantine-color-teal-6)" />
                                <div style={{flex: 1}}>
                                    <Text size="xs" c="dimmed" fw={500}>
                                        {t`Platform Revenue`}
                                    </Text>
                                    {isLoadingDashboard ? (
                                        <Skeleton height={28} width={80} mt={4} />
                                    ) : (
                                        <Text size="xl" fw={700}>
                                            {formatCurrency(dashboardData?.recent_revenue || 0)}
                                        </Text>
                                    )}
                                </div>
                            </Group>
                        </Paper>

                        <Paper shadow="sm" p="md" radius="md" withBorder>
                            <Group gap="xs">
                                <IconShoppingCart size={32} color="var(--mantine-color-cyan-6)" />
                                <div style={{flex: 1}}>
                                    <Text size="xs" c="dimmed" fw={500}>
                                        {t`Orders Completed`}
                                    </Text>
                                    {isLoadingDashboard ? (
                                        <Skeleton height={28} width={60} mt={4} />
                                    ) : (
                                        <Text size="xl" fw={700}>
                                            {formatNumber(dashboardData?.recent_orders_count || 0)}
                                        </Text>
                                    )}
                                </div>
                            </Group>
                        </Paper>

                        <Paper shadow="sm" p="md" radius="md" withBorder>
                            <Group gap="xs">
                                <IconCurrencyDollar size={32} color="var(--mantine-color-lime-6)" />
                                <div style={{flex: 1}}>
                                    <Text size="xs" c="dimmed" fw={500}>
                                        {t`Orders Total`}
                                    </Text>
                                    {isLoadingDashboard ? (
                                        <Skeleton height={28} width={80} mt={4} />
                                    ) : (
                                        <Text size="xl" fw={700}>
                                            {formatCurrency(dashboardData?.recent_orders_total || 0)}
                                        </Text>
                                    )}
                                </div>
                            </Group>
                        </Paper>

                        <Paper shadow="sm" p="md" radius="md" withBorder>
                            <Group gap="xs">
                                <IconUserPlus size={32} color="var(--mantine-color-pink-6)" />
                                <div style={{flex: 1}}>
                                    <Text size="xs" c="dimmed" fw={500}>
                                        {t`New Signups`}
                                    </Text>
                                    {isLoadingDashboard ? (
                                        <Skeleton height={28} width={60} mt={4} />
                                    ) : (
                                        <Text size="xl" fw={700}>
                                            {formatNumber(dashboardData?.recent_signups_count || 0)}
                                        </Text>
                                    )}
                                </div>
                            </Group>
                        </Paper>
                    </SimpleGrid>
                </div>

                {/* Popular Events */}
                <div>
                    <Title order={2} mb="md">
                        <Group gap="xs">
                            <IconTrendingUp size={24} />
                            <Trans>Popular Events (Last 14 Days)</Trans>
                        </Group>
                    </Title>

                    {isLoadingDashboard ? (
                        <Skeleton height={200} radius="md" />
                    ) : dashboardData?.popular_events && dashboardData.popular_events.length > 0 ? (
                        <Paper shadow="sm" radius="md" withBorder>
                            <Table striped highlightOnHover>
                                <Table.Thead>
                                    <Table.Tr>
                                        <Table.Th>{t`Event`}</Table.Th>
                                        <Table.Th>{t`Organizer`}</Table.Th>
                                        <Table.Th ta="right">{t`Products Sold`}</Table.Th>
                                        <Table.Th ta="right">{t`Gross Sales`}</Table.Th>
                                    </Table.Tr>
                                </Table.Thead>
                                <Table.Tbody>
                                    {dashboardData.popular_events.map((event) => (
                                        <Table.Tr key={event.id}>
                                            <Table.Td>
                                                <Text fw={500}>{event.title}</Text>
                                                <Text size="xs" c="dimmed">{event.account_name || '-'}</Text>
                                            </Table.Td>
                                            <Table.Td>{event.organizer_name || '-'}</Table.Td>
                                            <Table.Td ta="right">{formatNumber(event.products_sold)}</Table.Td>
                                            <Table.Td ta="right">{formatCurrency(event.sales_total_gross, event.currency)}</Table.Td>
                                        </Table.Tr>
                                    ))}
                                </Table.Tbody>
                            </Table>
                        </Paper>
                    ) : (
                        <Paper shadow="sm" p="xl" radius="md" withBorder>
                            <Stack align="center" gap="xs">
                                <IconTrendingUp size={48} color="var(--mantine-color-dimmed)" />
                                <Text size="lg" c="dimmed">
                                    <Trans>No popular events in the last 14 days</Trans>
                                </Text>
                            </Stack>
                        </Paper>
                    )}
                </div>

                {/* Most Viewed Events */}
                <div>
                    <Title order={2} mb="md">
                        <Group gap="xs">
                            <IconEye size={24} />
                            <Trans>Most Viewed Events (Last 14 Days)</Trans>
                        </Group>
                    </Title>

                    {isLoadingDashboard ? (
                        <Skeleton height={200} radius="md" />
                    ) : dashboardData?.most_viewed_events && dashboardData.most_viewed_events.length > 0 ? (
                        <Paper shadow="sm" radius="md" withBorder>
                            <Table striped highlightOnHover>
                                <Table.Thead>
                                    <Table.Tr>
                                        <Table.Th>{t`Event`}</Table.Th>
                                        <Table.Th>{t`Organizer`}</Table.Th>
                                        <Table.Th ta="right">{t`Total Views`}</Table.Th>
                                        <Table.Th ta="right">{t`Unique Views`}</Table.Th>
                                    </Table.Tr>
                                </Table.Thead>
                                <Table.Tbody>
                                    {dashboardData.most_viewed_events.map((event) => (
                                        <Table.Tr key={event.id}>
                                            <Table.Td>
                                                <Text fw={500}>{event.title}</Text>
                                                <Text size="xs" c="dimmed">{event.account_name || '-'}</Text>
                                            </Table.Td>
                                            <Table.Td>{event.organizer_name || '-'}</Table.Td>
                                            <Table.Td ta="right">{formatNumber(event.total_views)}</Table.Td>
                                            <Table.Td ta="right">{formatNumber(event.unique_views)}</Table.Td>
                                        </Table.Tr>
                                    ))}
                                </Table.Tbody>
                            </Table>
                        </Paper>
                    ) : (
                        <Paper shadow="sm" p="xl" radius="md" withBorder>
                            <Stack align="center" gap="xs">
                                <IconEye size={48} color="var(--mantine-color-dimmed)" />
                                <Text size="lg" c="dimmed">
                                    <Trans>No viewed events in the last 14 days</Trans>
                                </Text>
                            </Stack>
                        </Paper>
                    )}
                </div>

                {/* Top Organizers */}
                <div>
                    <Title order={2} mb="md">
                        <Group gap="xs">
                            <IconBuildingBank size={24} />
                            <Trans>Top Organizers (Last 14 Days)</Trans>
                        </Group>
                    </Title>

                    {isLoadingDashboard ? (
                        <Skeleton height={200} radius="md" />
                    ) : dashboardData?.top_organizers && dashboardData.top_organizers.length > 0 ? (
                        <Paper shadow="sm" radius="md" withBorder>
                            <Table striped highlightOnHover>
                                <Table.Thead>
                                    <Table.Tr>
                                        <Table.Th>{t`Organizer`}</Table.Th>
                                        <Table.Th>{t`Account`}</Table.Th>
                                        <Table.Th ta="right">{t`Active Events`}</Table.Th>
                                        <Table.Th ta="right">{t`Products Sold`}</Table.Th>
                                    </Table.Tr>
                                </Table.Thead>
                                <Table.Tbody>
                                    {dashboardData.top_organizers.map((organizer) => (
                                        <Table.Tr key={organizer.id}>
                                            <Table.Td>
                                                <Text fw={500}>{organizer.name}</Text>
                                            </Table.Td>
                                            <Table.Td>{organizer.account_name || '-'}</Table.Td>
                                            <Table.Td ta="right">{formatNumber(organizer.events_count)}</Table.Td>
                                            <Table.Td ta="right">{formatNumber(organizer.total_products_sold)}</Table.Td>
                                        </Table.Tr>
                                    ))}
                                </Table.Tbody>
                            </Table>
                        </Paper>
                    ) : (
                        <Paper shadow="sm" p="xl" radius="md" withBorder>
                            <Stack align="center" gap="xs">
                                <IconBuildingBank size={48} color="var(--mantine-color-dimmed)" />
                                <Text size="lg" c="dimmed">
                                    <Trans>No organizer activity in the last 14 days</Trans>
                                </Text>
                            </Stack>
                        </Paper>
                    )}
                </div>

                {/* Recent Account Signups */}
                <div>
                    <Title order={2} mb="md">
                        <Group gap="xs">
                            <IconUserPlus size={24} />
                            <Trans>Recent Account Signups</Trans>
                        </Group>
                    </Title>

                    {isLoadingDashboard ? (
                        <Skeleton height={200} radius="md" />
                    ) : dashboardData?.recent_accounts && dashboardData.recent_accounts.length > 0 ? (
                        <Paper shadow="sm" radius="md" withBorder>
                            <Table striped highlightOnHover>
                                <Table.Thead>
                                    <Table.Tr>
                                        <Table.Th>{t`Account`}</Table.Th>
                                        <Table.Th>{t`Email`}</Table.Th>
                                        <Table.Th>{t`Signed Up`}</Table.Th>
                                        <Table.Th ta="right">{t`Events`}</Table.Th>
                                        <Table.Th ta="right">{t`Users`}</Table.Th>
                                        <Table.Th>{t`Status`}</Table.Th>
                                    </Table.Tr>
                                </Table.Thead>
                                <Table.Tbody>
                                    {dashboardData.recent_accounts.map((account) => (
                                        <Table.Tr key={account.id}>
                                            <Table.Td>
                                                <Text fw={500}>{account.name}</Text>
                                            </Table.Td>
                                            <Table.Td>{account.email}</Table.Td>
                                            <Table.Td>{dayjs(account.created_at).fromNow()}</Table.Td>
                                            <Table.Td ta="right">{formatNumber(account.events_count)}</Table.Td>
                                            <Table.Td ta="right">{formatNumber(account.users_count)}</Table.Td>
                                            <Table.Td>
                                                <Group gap="xs">
                                                    {account.account_verified_at && (
                                                        <Badge size="xs" color="green" variant="light">{t`Verified`}</Badge>
                                                    )}
                                                    {account.stripe_connect_setup_complete && (
                                                        <Badge size="xs" color="blue" variant="light">{t`Stripe`}</Badge>
                                                    )}
                                                </Group>
                                            </Table.Td>
                                        </Table.Tr>
                                    ))}
                                </Table.Tbody>
                            </Table>
                        </Paper>
                    ) : (
                        <Paper shadow="sm" p="xl" radius="md" withBorder>
                            <Stack align="center" gap="xs">
                                <IconUserPlus size={48} color="var(--mantine-color-dimmed)" />
                                <Text size="lg" c="dimmed">
                                    <Trans>No recent account signups</Trans>
                                </Text>
                            </Stack>
                        </Paper>
                    )}
                </div>

                {/* Upcoming Events */}
                <div>
                    <Title order={2} mb="md">
                        <Group gap="xs">
                            <IconClock size={24} />
                            <Trans>Events Starting in Next 24 Hours</Trans>
                        </Group>
                    </Title>

                    {isLoadingEvents ? (
                        <Stack gap="md">
                            <Skeleton height={100} radius="md" />
                            <Skeleton height={100} radius="md" />
                            <Skeleton height={100} radius="md" />
                        </Stack>
                    ) : upcomingEvents?.data && upcomingEvents.data.length > 0 ? (
                        <Stack gap="md">
                            {upcomingEvents.data.map((event: any) => (
                                <Paper key={event.id} shadow="sm" p="md" radius="md" withBorder>
                                    <Group justify="space-between" align="center">
                                        <div style={{flex: 1}}>
                                            <Group gap="xs">
                                                <Text fw={600} size="lg">{event.title}</Text>
                                                <Badge color="orange" variant="light">
                                                    {formatEventDate(event.start_date, event.timezone)}
                                                </Badge>
                                            </Group>
                                        </div>
                                        <Anchor
                                            href={eventHomepageUrl(event)}
                                            target="_blank"
                                            size="sm"
                                        >
                                            <Trans>View Event</Trans>
                                        </Anchor>
                                    </Group>
                                </Paper>
                            ))}
                        </Stack>
                    ) : (
                        <Paper shadow="sm" p="xl" radius="md" withBorder>
                            <Stack align="center" gap="xs">
                                <IconCalendarEvent size={48} color="var(--mantine-color-dimmed)" />
                                <Text size="lg" c="dimmed">
                                    <Trans>No events starting in the next 24 hours</Trans>
                                </Text>
                            </Stack>
                        </Paper>
                    )}
                </div>
            </Stack>
        </Container>
    );
};

export default AdminDashboard;
