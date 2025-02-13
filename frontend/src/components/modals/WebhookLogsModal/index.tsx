import {useParams} from "react-router";
import {useGetWebhookLogs} from "../../../queries/useGetWebhookLogs";
import {Modal} from "../../common/Modal";
import {t} from "@lingui/macro";
import {Center} from "../../common/Center";
import {Alert, Badge, Code, Collapse, Group, Loader, Paper, Stack, Text} from "@mantine/core";
import {IconCheck, IconChevronDown, IconChevronRight, IconX} from '@tabler/icons-react';
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

    return (
        <Paper
            withBorder
            p="sm"
            mb="xs"
            onClick={() => setDetailsOpen(!detailsOpen)}
            style={{cursor: 'pointer'}}
        >
            <Group justify="space-between" wrap="nowrap">
                <Group wrap="nowrap">
                    {detailsOpen ? <IconChevronDown size={16}/> : <IconChevronRight size={16}/>}
                    <div>
                        <Group wrap="nowrap" mb={4}>
                            <Text fw={500} size="sm">
                                {log.event_type}
                            </Text>
                            <Badge
                                color={getStatusColor(log.response_code)}
                                variant="light"
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
                    log.response_code >= 200 && log.response_code < 300 ?
                        <IconCheck size={18} color="var(--mantine-color-green-6)"/> :
                        <IconX size={18} color="var(--mantine-color-red-6)"/>
                )}
            </Group>

            <Collapse in={detailsOpen}>
                <Stack mt="md">
                    {log.payload && (
                        <>
                            <Text size="sm" fw={500}>Payload:</Text>
                            <Code block>
                                {JSON.stringify(JSON.parse(log.payload), null, 2)}
                            </Code>
                        </>
                    )}

                    {log.response_body && (
                        <>
                            <Text size="sm" fw={500}>Response:</Text>
                            <Code block>
                                {JSON.stringify(JSON.parse(log.response_body), null, 2)}
                            </Code>
                        </>
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
            size="lg"
        >
            {logsQuery.isLoading && (
                <Center>
                    <Loader/>
                </Center>
            )}

            {!!logsQuery.error && (
                <Alert color="red">
                    {logsQuery.error.message}
                </Alert>
            )}

            {logs && logs.length === 0 && (
                <Text c="dimmed" ta="center" py="xl">
                    No logs found
                </Text>
            )}

            {logs && logs.length > 0 && (
                <Stack>
                    {logs.map((log) => (
                        <LogEntry key={log.id} log={log}/>
                    ))}
                </Stack>
            )}
        </Modal>
    );
};
