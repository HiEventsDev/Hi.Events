import {t} from "@lingui/macro";
import {Badge, Box, Group, HoverCard, Stack, Text} from "@mantine/core";
import {IconArrowRight, IconCheck, IconRefresh, IconUserCheck} from "@tabler/icons-react";
import {ReactNode} from "react";

export const statusColor = (status: string) => {
    switch (status?.toUpperCase()) {
        case 'DELIVERED':
            return 'green';
        case 'SENT':
            return 'teal';
        case 'BOUNCED':
            return 'red';
        case 'FAILED':
            return 'orange';
        case 'SUPPRESSED':
            return 'gray';
        default:
            return 'blue';
    }
};

export const emailTypeLabel = (emailType: string) => {
    switch (emailType) {
        case 'announcement':
            return t`Announcement`;
        case 'order_summary':
            return t`Order Confirmation`;
        case 'order_failed':
            return t`Order Failed`;
        case 'attendee_ticket':
            return t`Attendee Ticket`;
        case 'waitlist_offer':
            return t`Waitlist Offer`;
        case 'waitlist_confirmation':
            return t`Waitlist Confirmation`;
        case 'waitlist_offer_expired':
            return t`Waitlist Offer Expired`;
        case 'organizer_order_summary':
            return t`Organizer Order Notification`;
        default:
            return emailType;
    }
};

export const statusFilterOptions = [
    {label: 'SENT', value: 'SENT'},
    {label: 'DELIVERED', value: 'DELIVERED'},
    {label: 'BOUNCED', value: 'BOUNCED'},
    {label: 'FAILED', value: 'FAILED'},
    {label: 'SUPPRESSED', value: 'SUPPRESSED'},
    {label: 'RESOLVED', value: 'RESOLVED'},
];

export const dateRangeOptions = [
    {label: t`Day`, value: '1d'},
    {label: t`Week`, value: '7d'},
    {label: t`Month`, value: '30d'},
    {label: t`Qtr`, value: '90d'},
    {label: t`Year`, value: '365d'},
    {label: t`All`, value: 'all'},
];

export const emailTypeFilterOptions = [
    {label: t`Order Confirmation`, value: 'order_summary'},
    {label: t`Order Failed`, value: 'order_failed'},
    {label: t`Attendee Ticket`, value: 'attendee_ticket'},
    {label: t`Waitlist Offer`, value: 'waitlist_offer'},
    {label: t`Waitlist Confirmation`, value: 'waitlist_confirmation'},
    {label: t`Waitlist Offer Expired`, value: 'waitlist_offer_expired'},
    {label: t`Organizer Order Notification`, value: 'organizer_order_summary'},
];

const formatDate = (dateStr: string | null | undefined) => {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return d.toLocaleDateString(undefined, {year: 'numeric', month: 'short', day: 'numeric'});
};

export interface ResolvedInfo {
    recipient: string;
    status: string;
    resolved_at?: string | null;
    resolution_type?: string | null;
    latest_retry_recipient?: string | null;
    latest_retry_status?: string | null;
}

export interface RetryInfo {
    original_recipient?: string | null;
    original_status?: string | null;
    recipient: string;
    status: string;
}

export const RetryHoverCard = ({msg, children}: {msg: RetryInfo, children: ReactNode}) => {
    if (!msg.original_recipient) return <>{children}</>;

    return (
        <HoverCard width={340} shadow="md" withArrow position="bottom" openDelay={200}>
            <HoverCard.Target>
                {children}
            </HoverCard.Target>
            <HoverCard.Dropdown>
                <Stack gap="xs">
                    <Group gap="xs">
                        <IconRefresh size={16} color="var(--mantine-color-blue-6)"/>
                        <Text size="sm" fw={600}>{t`Retry Attempt`}</Text>
                    </Group>

                    <Box
                        p="xs"
                        style={{
                            backgroundColor: 'var(--mantine-color-gray-0)',
                            borderRadius: 'var(--mantine-radius-sm)',
                        }}
                    >
                        <Text size="xs" c="dimmed" mb={4}>{t`Original`}</Text>
                        <Group gap="xs">
                            <Text size="sm" style={{wordBreak: 'break-all'}}>{msg.original_recipient}</Text>
                            {msg.original_status && (
                                <Badge size="xs" color={statusColor(msg.original_status)} variant="filled">
                                    {msg.original_status}
                                </Badge>
                            )}
                        </Group>
                    </Box>

                    <Group gap={4} justify="center">
                        <IconArrowRight size={14} color="var(--mantine-color-dimmed)"/>
                    </Group>

                    <Box
                        p="xs"
                        style={{
                            backgroundColor: 'var(--mantine-color-blue-0)',
                            borderRadius: 'var(--mantine-radius-sm)',
                        }}
                    >
                        <Text size="xs" c="dimmed" mb={4}>{t`Retried as`}</Text>
                        <Group gap="xs">
                            <Text size="sm" style={{wordBreak: 'break-all'}}>{msg.recipient}</Text>
                            <Badge size="xs" color={statusColor(msg.status)} variant="filled">
                                {msg.status}
                            </Badge>
                        </Group>
                    </Box>
                </Stack>
            </HoverCard.Dropdown>
        </HoverCard>
    );
};

export const ResolvedHoverCard = ({msg, children}: {msg: ResolvedInfo, children: ReactNode}) => {
    if (!msg.resolved_at) return <>{children}</>;

    const date = formatDate(msg.resolved_at);
    const isManual = msg.resolution_type === 'manual' || (!msg.latest_retry_recipient && !msg.latest_retry_status);
    const hasRetryInfo = !!msg.latest_retry_recipient || !!msg.latest_retry_status;

    return (
        <HoverCard width={340} shadow="md" withArrow position="bottom" openDelay={200}>
            <HoverCard.Target>
                {children}
            </HoverCard.Target>
            <HoverCard.Dropdown>
                <Stack gap="xs">
                    <Group gap="xs">
                        {isManual
                            ? <IconUserCheck size={16} color="var(--mantine-color-blue-6)"/>
                            : <IconCheck size={16} color="var(--mantine-color-green-6)"/>
                        }
                        <Text size="sm" fw={600}>
                            {isManual ? t`Resolved Manually` : t`Auto-Resolved`}
                        </Text>
                        <Text size="xs" c="dimmed" ml="auto">{date}</Text>
                    </Group>

                    <Box
                        p="xs"
                        style={{
                            backgroundColor: 'var(--mantine-color-gray-0)',
                            borderRadius: 'var(--mantine-radius-sm)',
                        }}
                    >
                        <Text size="xs" c="dimmed" mb={4}>{t`Original`}</Text>
                        <Group gap="xs">
                            <Text size="sm" style={{wordBreak: 'break-all'}}>{msg.recipient}</Text>
                            <Badge size="xs" color={statusColor(msg.status)} variant="filled">
                                {msg.status}
                            </Badge>
                        </Group>
                    </Box>

                    {hasRetryInfo && !isManual && (
                        <>
                            <Group gap={4} justify="center">
                                <IconArrowRight size={14} color="var(--mantine-color-dimmed)"/>
                            </Group>
                            <Box
                                p="xs"
                                style={{
                                    backgroundColor: 'var(--mantine-color-green-0)',
                                    borderRadius: 'var(--mantine-radius-sm)',
                                }}
                            >
                                <Text size="xs" c="dimmed" mb={4}>{t`Resent to`}</Text>
                                <Group gap="xs">
                                    <Text size="sm" style={{wordBreak: 'break-all'}}>{msg.latest_retry_recipient}</Text>
                                    {msg.latest_retry_status && (
                                        <Badge size="xs" color={statusColor(msg.latest_retry_status)} variant="filled">
                                            {msg.latest_retry_status}
                                        </Badge>
                                    )}
                                </Group>
                            </Box>
                        </>
                    )}
                </Stack>
            </HoverCard.Dropdown>
        </HoverCard>
    );
};
