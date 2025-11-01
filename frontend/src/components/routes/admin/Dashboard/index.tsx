import {Container, Title, Text, Paper, Stack, Group, SimpleGrid, Skeleton} from "@mantine/core";
import {t, Trans} from "@lingui/macro";
import {IconUsers, IconBuildingBank, IconCalendarEvent, IconTicket} from "@tabler/icons-react";
import {useGetMe} from "../../../../queries/useGetMe";
import {useGetAdminStats} from "../../../../queries/useGetAdminStats";

const AdminDashboard = () => {
    const {data: user} = useGetMe();
    const {data: stats, isLoading} = useGetAdminStats();
    
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
            </Stack>
        </Container>
    );
};

export default AdminDashboard;
