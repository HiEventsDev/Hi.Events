import {t} from "@lingui/macro";
import {Box, Group, Stack, Text, Title} from "@mantine/core";
import {Card} from "../../../common/Card";
import {AccountConfiguration} from "../../../../types";
import {formatCurrency} from "../../../../utilites/currency";
import {getConfig} from "../../../../utilites/config";

interface FeePlanCardProps {
    configuration?: AccountConfiguration;
}

const formatPercentage = (value: number): string => `${(value).toFixed(2)}%`;

export const FeePlanCard = ({configuration}: FeePlanCardProps) => {
    if (!configuration) return null;

    const {percentage, fixed, currency} = configuration.application_fees;
    if (!percentage && !fixed) return null;

    const platformName = getConfig("VITE_APP_NAME", "Hi.Events");

    return (
        <Card>
            <Title order={4} mb={4}>{t`Platform fees`}</Title>
            <Text size="sm" c="dimmed" mb="md">
                {t`${platformName} keeps a small cut of each transaction to run the service. Fees are deducted automatically.`}
            </Text>
            <Stack gap="xs">
                {percentage > 0 && (
                    <Group justify="space-between">
                        <Text size="sm">{t`Transaction fee`}</Text>
                        <Text size="sm" fw={600}>{formatPercentage(percentage)}</Text>
                    </Group>
                )}
                {fixed > 0 && (
                    <Group justify="space-between">
                        <Text size="sm">{t`Fixed fee`}</Text>
                        <Text size="sm" fw={600}>
                            {formatCurrency(fixed, currency || "USD")}
                        </Text>
                    </Group>
                )}
            </Stack>
            <Box mt="md">
                <Text size="xs" c="dimmed">
                    {t`Fees are subject to change. You'll be notified of any changes to your fee structure.`}
                </Text>
            </Box>
        </Card>
    );
};
