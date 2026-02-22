import {WaitlistStats as WaitlistStatsType} from "../../../types.ts";
import {Paper, SimpleGrid, Text} from "@mantine/core";
import {t} from "@lingui/macro";

interface WaitlistStatsProps {
    stats: WaitlistStatsType;
}

export const WaitlistStatsCards = ({stats}: WaitlistStatsProps) => {
    const statItems = [
        {label: t`Total Entries`, value: stats.total},
        {label: t`Waiting`, value: stats.waiting},
        {label: t`Offered`, value: stats.offered},
        {label: t`Purchased`, value: stats.purchased},
    ];

    return (
        <SimpleGrid cols={{base: 2, sm: 4}} mb="md" visibleFrom="sm">
            {statItems.map((item) => (
                <Paper key={item.label} withBorder p="md" radius="md">
                    <Text size="xs" c="dimmed" tt="uppercase" fw={700}>
                        {item.label}
                    </Text>
                    <Text size="xl" fw={700} mt={4}>
                        {item.value}
                    </Text>
                </Paper>
            ))}
        </SimpleGrid>
    );
};
