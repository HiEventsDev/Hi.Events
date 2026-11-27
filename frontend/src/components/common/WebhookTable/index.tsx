import {ActionIcon, Anchor, Menu, Paper, Popover, Stack, Text, Tooltip, UnstyledButton, VisuallyHidden} from '@mantine/core';
import {WriteAccess} from "../WriteAccess";
import {CreateButton} from "../CreateButton";
import {IconClipboardList, IconDotsVertical, IconPencil, IconPlus, IconTrash} from '@tabler/icons-react';
import classes from './WebhookTable.module.scss';
import {IdParam, Webhook} from '../../../types';
import {confirmationDialog} from '../../../utilites/confirmationDialog';
import {relativeDate} from "../../../utilites/dates.ts";
import {ComponentType, useState} from "react";
import {t, Trans} from "@lingui/macro";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";
import {NoResultsSplash} from "../NoResultsSplash";
import {TanStackTable, TanStackTableColumn} from "../TanStackTable";
import {CellCopy, CellText, EmptyCell} from "../TanStackTable/cells.tsx";
import {StatusBadge} from "../StatusBadge";

interface WebhookModalProps {
    onClose: () => void;
    webhookId: IdParam;
}

interface WebhookTableProps {
    webhooks: Webhook[];
    openCreateModal: () => void;
    deleteWebhook: (webhookId: IdParam) => Promise<unknown>;
    EditModal: ComponentType<WebhookModalProps>;
    LogsModal: ComponentType<WebhookModalProps>;
}

const isSuccessfulResponse = (code: number) => code >= 200 && code < 300;

const LastResponse = ({webhook}: { webhook: Webhook }) => {
    const code = webhook.last_response_code;
    if (code === null || code === undefined) {
        return <CellText muted>{t`No responses yet`}</CellText>;
    }
    const tone = isSuccessfulResponse(code) ? 'positive' : 'negative';

    return (
        <Popover width={400} position="bottom-start" shadow="md">
            <Popover.Target>
                <UnstyledButton>
                    <StatusBadge tone={tone}>
                        {isSuccessfulResponse(code) ? t`Success` : t`Error`}{code > 0 ? ` · ${code}` : ''}
                    </StatusBadge>
                </UnstyledButton>
            </Popover.Target>

            <Popover.Dropdown>
                <Stack gap="md">
                    <div className={classes.responseHeader}>
                        <Text fw={500} size="sm">{t`Response Details`}</Text>
                        <StatusBadge tone={tone}>{code > 0 ? code : t`No response`}</StatusBadge>
                    </div>

                    {webhook.last_response_body && (
                        <Paper className={classes.responseBody} withBorder p="xs">
                            <pre>{webhook.last_response_body}</pre>
                        </Paper>
                    )}
                </Stack>
            </Popover.Dropdown>
        </Popover>
    );
};

export const WebhookTable = ({webhooks, openCreateModal, deleteWebhook, EditModal, LogsModal}: WebhookTableProps) => {
    const [openModal, setOpenModal] = useState<{ type: 'edit' | 'logs', webhookId: IdParam } | null>(null);

    const handleDelete = (webhookId: IdParam) => {
        deleteWebhook(webhookId)
            .then(() => showSuccess(t`Webhook deleted successfully`))
            .catch((error: Error) => showError(error.message));
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
                        <CreateButton
                            size={'xs'}
                            leftSection={<IconPlus/>}
                            color={'green'}
                            onClick={() => openCreateModal()}>{t`Add Webhook`}
                        </CreateButton>
                    </>
                )}
            />
        );
    }

    const columns: TanStackTableColumn<Webhook>[] = [
        {
            id: 'url',
            header: t`URL`,
            enableHiding: false,
            meta: {sticky: 'left'},
            cell: ({row: {original: webhook}}) => (
                <CellCopy value={webhook.url}>
                    <CellText strong title={webhook.url}>{webhook.url}</CellText>
                </CellCopy>
            ),
        },
        {
            id: 'eventTypes',
            header: t`Event Types`,
            cell: ({row: {original: webhook}}) => {
                const eventTypes = webhook.event_types ?? [];
                if (eventTypes.length === 0) {
                    return <EmptyCell/>;
                }
                if (eventTypes.length === 1) {
                    return <CellText mono>{eventTypes[0]}</CellText>;
                }
                const eventCount = eventTypes.length;

                return (
                    <Tooltip label={eventTypes.join('\n')} multiline withArrow style={{whiteSpace: 'pre-line'}}>
                        <span><Trans>{eventCount} events</Trans></span>
                    </Tooltip>
                );
            },
        },
        {
            id: 'status',
            header: t`Status`,
            cell: ({row: {original: webhook}}) => (
                <StatusBadge tone={webhook.status === 'ENABLED' ? 'positive' : 'neutral'}>
                    {webhook.status === 'ENABLED' ? t`Enabled` : t`Paused`}
                </StatusBadge>
            ),
        },
        {
            id: 'lastResponse',
            header: t`Last Response`,
            meta: {interactive: true},
            cell: ({row: {original: webhook}}) => <LastResponse webhook={webhook}/>,
        },
        {
            id: 'lastTriggered',
            header: t`Last Triggered`,
            cell: ({row: {original: webhook}}) => webhook.last_triggered_at
                ? <span title={webhook.last_triggered_at as string}>{relativeDate(webhook.last_triggered_at as string)}</span>
                : <CellText muted>{t`Never`}</CellText>,
        },
        {
            id: 'actions',
            header: () => <VisuallyHidden>{t`Actions`}</VisuallyHidden>,
            enableHiding: false,
            meta: {sticky: 'right'},
            cell: ({row: {original: webhook}}) => (
                <Menu shadow="md" width={200} position="bottom-end">
                    <Menu.Target>
                        <ActionIcon variant="subtle" color="gray" aria-label={t`Actions`} data-testid="webhook-actions-button">
                            <IconDotsVertical size={18}/>
                        </ActionIcon>
                    </Menu.Target>

                    <Menu.Dropdown>
                        <Menu.Label>{t`Manage`}</Menu.Label>
                        <WriteAccess>
                            <Menu.Item
                                leftSection={<IconPencil size={14}/>}
                                onClick={() => setOpenModal({type: 'edit', webhookId: webhook.id as IdParam})}
                            >
                                {t`Edit webhook`}
                            </Menu.Item>
                        </WriteAccess>
                        <Menu.Item
                            leftSection={<IconClipboardList size={14}/>}
                            onClick={() => setOpenModal({type: 'logs', webhookId: webhook.id as IdParam})}
                        >
                            {t`View logs`}
                        </Menu.Item>
                        <WriteAccess>
                            <Menu.Divider/>
                            <Menu.Label>{t`Danger zone`}</Menu.Label>
                            <Menu.Item
                                color="red"
                                leftSection={<IconTrash size={14}/>}
                                onClick={() => confirmationDialog(
                                    t`Are you sure you want to delete this webhook?`,
                                    () => handleDelete(webhook.id as IdParam)
                                )}
                            >
                                {t`Delete webhook`}
                            </Menu.Item>
                        </WriteAccess>
                    </Menu.Dropdown>
                </Menu>
            ),
        },
    ];

    return (
        <>
            <TanStackTable data={webhooks} columns={columns}/>
            {openModal?.type === 'logs' && <LogsModal onClose={() => setOpenModal(null)} webhookId={openModal.webhookId}/>}
            {openModal?.type === 'edit' && <EditModal onClose={() => setOpenModal(null)} webhookId={openModal.webhookId}/>}
        </>
    );
};
