import {useParams} from "react-router";
import {useGetWebhookLogs} from "../../../queries/useGetWebhookLogs";
import {Modal} from "../../common/Modal";
import {t} from "@lingui/macro";
import {Center} from "../../common/Center";
import {Alert, Badge, Code, Collapse, Group, Loader, Paper, Stack, Text} from "@mantine/core";
import {IconCheck, IconChevronRight, IconX} from '@tabler/icons-react';
import {GenericModalProps, IdParam} from "../../../types.ts";
import {useState} from "react";
import {relativeDate} from "../../../utilites/dates.ts";

interface WebhookLog {
    id: IdParam;
    webhook_id: IdParam;
    payload?: string;
    response_code?: number;
    response_body?: string;
    event_type: string;
    created_at: string;
}

interface WebhookLogsModalProps extends GenericModalProps {
    webhookId: IdParam;
}

const LogEntry = ({log}: { log: WebhookLog }) => {
    const [detailsOpen, setDetailsOpen] = useState(false);

    const getStatusColor = (code?: number) => {
        if (!code) return 'gray';
        if (code >= 200 && code < 300) return 'green';
        if (code >= 300 && code < 400) return 'blue';
        return 'red';
    };

    const formatContent = (content?: string) => {
        if (!content) return '';

        try {
            return JSON.stringify(JSON.parse(content), null, 2);
        } catch (e) {
            return content;
        }
    };

    return (
        <Paper
            withBorder
            p="md"
            mb="md"
            onClick={() => setDetailsOpen(!detailsOpen)}
            style={{
                cursor: 'pointer',
                transition: 'all 0.2s ease',
                boxShadow: detailsOpen ? '0 4px 8px rgba(0, 0, 0, 0.1)' : 'none',
                borderRadius: '8px',
                borderLeft: `4px solid var(--mantine-color-${getStatusColor(log.response_code)}-6)`
            }}
        >
            <Group justify="space-between" wrap="nowrap">
                <Group wrap="nowrap" gap="md">
                    <div style={{
                        color: `var(--mantine-color-${getStatusColor(log.response_code)}-6)`,
                        transition: 'transform 0.2s ease',
                        transform: detailsOpen ? 'rotate(90deg)' : 'rotate(0deg)'
                    }}>
                        <IconChevronRight size={20}/>
                    </div>
                    <div>
                        <Group wrap="nowrap" mb={6} gap="xs">
                            <Text fw={600} size="sm">
                                {log.event_type}
                            </Text>
                            <Badge
                                color={getStatusColor(log.response_code)}
                                variant="filled"
                                size="sm"
                                radius="sm"
                            >
                                {log.response_code || 'No Response'}
                            </Badge>
                        </Group>
                        <Text size="xs" c="dimmed">
                            {relativeDate(log.created_at)}
                        </Text>
                    </div>
                </Group>
                {log.response_code && (
                    <div style={{
                        background: `var(--mantine-color-${getStatusColor(log.response_code)}-0)`,
                        color: `var(--mantine-color-${getStatusColor(log.response_code)}-6)`,
                        borderRadius: '50%',
                        width: '28px',
                        height: '28px',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center'
                    }}>
                        {log.response_code >= 200 && log.response_code < 300 ?
                            <IconCheck size={18}/> :
                            <IconX size={18}/>
                        }
                    </div>
                )}
            </Group>

            <Collapse in={detailsOpen} transitionDuration={300}>
                <Stack mt="lg" gap="md">
                    {log.payload && (
                        <div>
                            <Text size="sm" fw={500} mb={8} c="dimmed">Payload:</Text>
                            <Code block p="md" style={{
                                borderRadius: '6px',
                                maxHeight: '300px',
                                overflow: 'auto',
                                backgroundColor: 'var(--mantine-color-gray-0)'
                            }}>
                                {formatContent(log.payload)}
                            </Code>
                        </div>
                    )}

                    {log.response_body && (
                        <div>
                            <Text size="sm" fw={500} mb={8} c="dimmed">Response:</Text>
                            <Code block p="md" style={{
                                borderRadius: '6px',
                                maxHeight: '300px',
                                overflow: 'auto',
                                backgroundColor: 'var(--mantine-color-gray-0)'
                            }}>
                                {formatContent(log.response_body)}
                            </Code>
                        </div>
                    )}
                </Stack>
            </Collapse>
        </Paper>
    );
};

export const WebhookLogsModal = ({onClose, webhookId}: WebhookLogsModalProps) => {
    const {eventId} = useParams();
    const logsQuery = useGetWebhookLogs(eventId, webhookId);
    const logs = logsQuery.data?.data?.data;

    return (
        <Modal
            opened
            onClose={onClose}
            heading={t`Webhook Logs`}
            size="xl"
        >
            {logsQuery.isLoading && (
                <Center>
                    <Stack align="center" gap="xs">
                        <Loader size="md"/>
                        <Text size="sm" c="dimmed">Loading webhook logs...</Text>
                    </Stack>
                </Center>
            )}

            {!!logsQuery.error && (
                <Alert
                    color="red"
                    title={t`Error loading logs`}
                    icon={<IconX size={18}/>}
                    radius="md"
                >
                    {logsQuery.error.message}
                </Alert>
            )}

            {logs && logs.length === 0 && !logsQuery.isLoading && (
                <Alert style={{textAlign: 'center'}} radius="md">
                    <h2>
                        {t`No logs found`}
                    </h2>
                    <p>
                        {t`No webhook events have been recorded for this endpoint yet. Events will appear here once they are triggered.`}
                    </p>
                </Alert>
            )}

            {logs && logs.length > 0 && (
                <>
                    {logs.map((log) => (
                        <LogEntry key={log.id} log={log}/>
                    ))}
                </>
            )}
        </Modal>
    );
};
