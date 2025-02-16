import {
    Anchor,
    Badge,
    Button,
    Group,
    Menu,
    Paper,
    Popover,
    Stack,
    Table as MantineTable,
    Text,
    Tooltip
} from '@mantine/core';
import {
    IconBolt,
    IconClipboardList,
    IconClockHour4,
    IconDotsVertical,
    IconPencil,
    IconPlus,
    IconTrash
} from '@tabler/icons-react';
import {Table, TableHead} from '../Table';
import classes from './WebhookTable.module.scss';
import {IdParam, Webhook} from '../../../types';
import {confirmationDialog} from '../../../utilites/confirmationDialog';
import Truncate from '../Truncate';
import {relativeDate} from "../../../utilites/dates.ts";
import {useDisclosure} from "@mantine/hooks";
import {useState} from "react";
import {t, Trans} from "@lingui/macro";
import {EditWebhookModal} from "../../modals/EditWebhookModal";
import {useDeleteWebhook} from "../../../mutations/useDeleteWebhook.ts";
import {useParams} from "react-router";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";
import {NoResultsSplash} from "../NoResultsSplash";
import {WebhookLogsModal} from "../../modals/WebhookLogsModal";

interface WebhookTableProps {
    webhooks: Webhook[];
    openCreateModal: () => void;
}

export const WebhookTable = ({webhooks, openCreateModal}: WebhookTableProps) => {
    const {eventId} = useParams();
    const [editModalOpen, {open: openEditModal, close: closeEditModal}] = useDisclosure(false);
    const [logsModalOpen, {open: openLogsModal, close: closeLogsModal}] = useDisclosure(false);
    const [selectedWebhookId, setSelectedWebhookId] = useState<IdParam>();
    const deleteMutation = useDeleteWebhook();

    const handleDelete = (webhookId: IdParam) => {
        deleteMutation.mutate({eventId, webhookId}, {
            onSuccess: () => showSuccess(t`Webhook deleted successfully`),
            onError: (error) => showError(error.message)
        });
    }

    const EventTypeDisplay = ({webhook}: { webhook: Webhook }) => {
        const eventTypes = webhook.event_types;

        if (!eventTypes || eventTypes.length === 0) {
            return <>-</>;
        }

        const eventCount = eventTypes.length;

        return (
            <div style={{cursor: 'pointer'}}>
                <Tooltip
                    label={
                        <div className={classes.tooltipContent}>
                            {eventTypes.map((type) => (
                                <div key={type}>{type}</div>
                            ))}
                        </div>
                    }
                >
                    <Badge variant="light" size="sm">
                        {eventCount > 1 ? <Trans>{eventCount} events</Trans> : eventTypes[0]}
                    </Badge>
                </Tooltip>
            </div>
        );
    };

    const ActionMenu = ({webhook}: { webhook: Webhook }) => (
        <Group wrap="nowrap" gap={0} justify="flex-end">
            <Menu shadow="md" width={200}>
                <Menu.Target>
                    <Button size="xs" variant="transparent">
                        <IconDotsVertical/>
                    </Button>
                </Menu.Target>

                <Menu.Dropdown>
                    <Menu.Label>Manage</Menu.Label>
                    <Menu.Item
                        leftSection={<IconPencil size={14}/>}
                        onClick={() => {
                            setSelectedWebhookId(webhook.id as IdParam);
                            openEditModal();
                        }}
                    >
                        {t`Edit webhook`}
                    </Menu.Item>
                    <Menu.Item
                        leftSection={<IconClipboardList size={14}/>}
                        onClick={() => {
                            setSelectedWebhookId(webhook.id as IdParam);
                            openLogsModal();
                        }}
                    >
                        {t`View logs`}
                    </Menu.Item>
                    <Menu.Divider/>
                    <Menu.Label>{t`Danger zone`}</Menu.Label>
                    <Menu.Item
                        color="red"
                        leftSection={<IconTrash size={14}/>}
                        onClick={() => {
                            confirmationDialog(
                                t`Are you sure you want to delete this webhook?`,
                                () => handleDelete(webhook.id as IdParam)
                            );
                        }}
                    >
                        {t`Delete webhook`}
                    </Menu.Item>
                </Menu.Dropdown>
            </Menu>
        </Group>
    );


    const ResponseDisplay = ({webhook}: { webhook: Webhook }) => {
        if (webhook.last_response_code === null || webhook.last_response_code === undefined) {
            return (
                <Text c="dimmed" size="sm">
                    <Group gap={6} wrap="nowrap">
                        <IconClockHour4 size={14}/>
                        <span>{t`No responses yet`}</span>
                    </Group>
                </Text>
            );
        }

        const isSuccess = (webhook.last_response_code >= 200 && webhook.last_response_code < 300) && webhook.last_response_code !== 0;
        const statusColor = isSuccess ? 'green' : 'red';
        const statusText = isSuccess ? 'Success' : 'Error';

        return (
            <Popover width={400} position="bottom" withArrow>
                <Popover.Target>
                    <Group gap="xs" wrap="nowrap">
                        <Badge
                            variant="light"
                            color={statusColor}
                            leftSection={<IconBolt size={12}/>}
                        >
                            {statusText} {webhook.last_response_code > 0 ? `- ${webhook.last_response_code}` : ''}
                        </Badge>
                    </Group>
                </Popover.Target>

                <Popover.Dropdown>
                    <Stack gap="md">
                        <Group justify="space-between" align="center">
                            <Text fw={500} size="sm">Response Details</Text>
                            <Badge
                                variant="light"
                                color={statusColor}
                                size="sm"
                            >
                                {webhook.last_response_code > 0 ? webhook.last_response_code : t`No response`}
                            </Badge>
                        </Group>

                        {webhook.last_response_body && (
                            <Paper className={classes.responseBody} withBorder p="xs">
                                <Text
                                    component="pre"
                                    style={{
                                        margin: 0,
                                        whiteSpace: 'pre-wrap',
                                        wordBreak: 'break-word'
                                    }}
                                >
                                    {webhook.last_response_body}
                                </Text>
                            </Paper>
                        )}
                    </Stack>
                </Popover.Dropdown>
            </Popover>
        );
    };

    if (webhooks.length === 0) {
        return (
            <NoResultsSplash
                heading={t`No Webhooks`}
                imageHref={'/blank-slate/webhooks.svg'}
                subHeading={(
                    <>
                        <Trans>
                            <p>
                                Webhooks instantly notify external services when events happen, like adding a new attendee
                                to your CRM or mailing list upon registration, ensuring seamless automation.
                            </p>
                            <p>
                                Use third-party services like <Anchor underline={'always'} target={'_blank'}
                                                                      href="https://zapier.com/features/webhooks">Zapier</Anchor>,{' '}
                                <Anchor underline={'always'} target={'_blank'} href="https://ifttt.com/maker_webhooks">IFTTT</Anchor> or <Anchor underline={'always'}
                                target={'_blank'} href="https://www.make.com/en/help/tools/webhooks">Make</Anchor> to
                                create custom workflows and automate tasks.
                            </p>
                        </Trans>
                        <Button
                            loading={deleteMutation.isPending}
                            size={'xs'}
                            leftSection={<IconPlus/>}
                            color={'green'}
                            onClick={() => openCreateModal()}>{t`Add Webhook`}
                        </Button>
                    </>
                )}
            />
        );
    }

    return (
        <>
            <Table>
                <TableHead>
                    <MantineTable.Tr>
                        <MantineTable.Th>{t`URL`}</MantineTable.Th>
                        <MantineTable.Th>{t`Event Types`}</MantineTable.Th>
                        <MantineTable.Th miw={120}>{t`Status`}</MantineTable.Th>
                        <MantineTable.Th miw={140}>{t`Last Response`}</MantineTable.Th>
                        <MantineTable.Th>{t`Last Triggered`}</MantineTable.Th>
                        <MantineTable.Th></MantineTable.Th>
                    </MantineTable.Tr>
                </TableHead>
                <MantineTable.Tbody>
                    {webhooks.map((webhook) => (
                        <MantineTable.Tr key={webhook.id}>
                            <MantineTable.Td>
                                <Truncate text={webhook.url} length={35}/>
                            </MantineTable.Td>
                            <MantineTable.Td>
                                <EventTypeDisplay webhook={webhook}/>
                            </MantineTable.Td>
                            <MantineTable.Td>
                                <Badge
                                    variant="light"
                                    color={webhook.status === 'ENABLED' ? 'green' : 'gray'}
                                >
                                    {webhook.status}
                                </Badge>
                            </MantineTable.Td>
                            <MantineTable.Td>
                                <ResponseDisplay webhook={webhook}/>
                            </MantineTable.Td>
                            <MantineTable.Td>
                                <Text size="sm" c="dimmed" title={webhook.last_triggered_at as string}>
                                    {webhook.last_triggered_at ? relativeDate(webhook.last_triggered_at as string) : t`Never`}
                                </Text>
                            </MantineTable.Td>
                            <MantineTable.Td>
                                <ActionMenu webhook={webhook}/>
                            </MantineTable.Td>
                        </MantineTable.Tr>
                    ))}
                </MantineTable.Tbody>
            </Table>
            {logsModalOpen && selectedWebhookId && (
                <WebhookLogsModal
                    onClose={closeLogsModal}
                    webhookId={selectedWebhookId as IdParam}
                />
            )}

            {(editModalOpen && selectedWebhookId) && (
                <EditWebhookModal
                    onClose={closeEditModal}
                    webhookId={selectedWebhookId as IdParam}
                />
            )}
        </>
    );
};
