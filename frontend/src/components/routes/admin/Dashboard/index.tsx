import {Container, Title, Text, Paper, Stack, Group, SimpleGrid, Skeleton, Badge, Anchor} from "@mantine/core";
import {t, Trans} from "@lingui/macro";
import {IconUsers, IconBuildingBank, IconCalendarEvent, IconTicket, IconClock} from "@tabler/icons-react";
import {useGetMe} from "../../../../queries/useGetMe";
import {useGetAdminStats} from "../../../../queries/useGetAdminStats";
import {useGetUpcomingEvents} from "../../../../queries/useGetUpcomingEvents";
import {eventHomepageUrl} from "../../../../utilites/urlHelper";
import dayjs from "dayjs";
import utc from 'dayjs/plugin/utc';
import timezone from 'dayjs/plugin/timezone';

dayjs.extend(utc);
dayjs.extend(timezone);

const AdminDashboard = () => {
    const {data: user} = useGetMe();
    const {data: stats, isLoading} = useGetAdminStats();
    const {data: upcomingEvents, isLoading: isLoadingEvents} = useGetUpcomingEvents(10);

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
