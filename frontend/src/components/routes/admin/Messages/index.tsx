import {Container, Title, TextInput, Stack, Text, Badge, Group, Menu, Select, Modal, ScrollArea, ActionIcon, Tooltip, VisuallyHidden} from "@mantine/core";
import {t} from "@lingui/macro";
import {IconDotsVertical, IconSearch, IconEye, IconCheck} from "@tabler/icons-react";
import {useState, useEffect, useMemo} from "react";
import {useGetAdminMessages} from "../../../../queries/useGetAdminMessages";
import {useApproveMessage} from "../../../../mutations/useApproveMessage";
import {relativeDate} from "../../../../utilites/dates";
import {useDisclosure} from "@mantine/hooks";
import {AdminMessage} from "../../../../api/admin.client";
import {showSuccess} from "../../../../utilites/notifications";
import {IdParam} from "../../../../types";
import {TanStackTable, TanStackTableColumn} from "../../../common/TanStackTable";
import {CellText, EmptyCell} from "../../../common/TanStackTable/cells.tsx";
import {StatusBadge, StatusTone} from "../../../common/StatusBadge";
import {TableCard, TableCardFooter, TableCardSkeleton} from "../../../common/TableCard";
import {ToolBar} from "../../../common/ToolBar";
import tableStyles from "../../../../styles/admin-table.module.scss";

const STATUS_TONES: Record<string, StatusTone> = {
    sent: 'positive',
    pending: 'waiting',
    pending_review: 'waiting',
    failed: 'negative',
    scheduled: 'active',
    queued: 'active',
};

const Messages = () => {
    const [page, setPage] = useState(1);
    const [search, setSearch] = useState("");
    const [debouncedSearch, setDebouncedSearch] = useState("");
    const [statusFilter, setStatusFilter] = useState<string | null>(null);
    const [typeFilter, setTypeFilter] = useState<string | null>(null);
    const [selectedMessage, setSelectedMessage] = useState<AdminMessage | null>(null);
    const [detailModalOpened, {open: openDetailModal, close: closeDetailModal}] = useDisclosure(false);

    const {data: messagesData, isLoading} = useGetAdminMessages({
        page,
        per_page: 20,
        search: debouncedSearch,
        status: statusFilter || undefined,
        type: typeFilter || undefined,
    });

    const approveMutation = useApproveMessage();

    const handleApprove = (messageId: IdParam) => {
        approveMutation.mutate(messageId, {
            onSuccess: () => {
                showSuccess(t`Message approved successfully`);
            },
        });
    };

    useEffect(() => {
        const timer = setTimeout(() => {
            setDebouncedSearch(search);
            setPage(1);
        }, 500);

        return () => clearTimeout(timer);
    }, [search]);

    const getStatusColor = (status: string) => {
        switch (status.toLowerCase()) {
            case 'sent':
                return 'green';
            case 'pending':
            case 'pending_review':
                return 'yellow';
            case 'failed':
                return 'red';
            case 'scheduled':
                return 'blue';
            case 'cancelled':
                return 'gray';
            case 'queued':
                return 'blue';
            default:
                return 'gray';
        }
    };

    const getEligibilityFailureLabel = (failure: string): string => {
        switch (failure) {
            case 'stripe_not_connected':
                return t`Stripe Not Connected`;
            case 'no_paid_orders':
                return t`No Paid Orders`;
            case 'event_too_new':
                return t`Event Too New`;
            default:
                return failure;
        }
    };

    const getTypeLabel = (type: string) => {
        switch (type.toUpperCase()) {
            case 'TICKET':
                return t`Ticket Holders`;
            case 'ORDER':
                return t`Order Holders`;
            case 'ATTENDEE':
                return t`Attendees`;
            default:
                return type;
        }
    };

    const handleViewMessage = (message: AdminMessage) => {
        setSelectedMessage(message);
        openDetailModal();
    };

    const totalMessages = messagesData?.meta?.total || 0;

    const columns = useMemo<TanStackTableColumn<AdminMessage>[]>(() => [
        {
            id: 'subject',
            header: t`Subject`,
            meta: {sticky: 'left'},
            cell: ({row: {original: message}}) => <CellText strong title={message.subject}>{message.subject}</CellText>,
        },
        {
            id: 'event',
            header: t`Event`,
            cell: ({row: {original: message}}) => (
                <CellText maxWidth={200} title={message.event_title}>{message.event_title}</CellText>
            ),
        },
        {
            id: 'account',
            header: t`Account`,
            cell: ({row: {original: message}}) => (
                <CellText muted maxWidth={160} title={message.account_name}>{message.account_name}</CellText>
            ),
        },
        {
            id: 'type',
            header: t`Type`,
            cell: ({row: {original: message}}) => getTypeLabel(message.type),
        },
        {
            id: 'recipients',
            header: t`Recipients`,
            meta: {align: 'right'},
            cell: ({row: {original: message}}) => message.recipients_count,
        },
        {
            id: 'status',
            header: t`Status`,
            cell: ({row: {original: message}}) => (
                <StatusBadge tone={STATUS_TONES[message.status.toLowerCase()] ?? 'neutral'}>{message.status}</StatusBadge>
            ),
        },
        {
            id: 'sent_by',
            header: t`Sent By`,
            cell: ({row: {original: message}}) => message.sent_by
                ? <CellText muted maxWidth={180} title={message.sent_by}>{message.sent_by}</CellText>
                : <EmptyCell/>,
        },
        {
            id: 'created',
            header: t`Created`,
            cell: ({row: {original: message}}) => (
                <span title={message.created_at}>{relativeDate(message.created_at)}</span>
            ),
        },
        {
            id: 'actions',
            header: () => <VisuallyHidden>{t`Actions`}</VisuallyHidden>,
            meta: {sticky: 'right'},
            cell: ({row: {original: message}}) => (
                <Group gap={4} wrap="nowrap" justify="flex-end">
                    {message.status === 'PENDING_REVIEW' && (
                        <Tooltip label={t`Approve Message`}>
                            <ActionIcon
                                variant="subtle"
                                color="green"
                                aria-label={t`Approve Message`}
                                data-testid="admin-approve-message-button"
                                onClick={() => handleApprove(message.id)}
                                loading={approveMutation.isPending}
                            >
                                <IconCheck size={18}/>
                            </ActionIcon>
                        </Tooltip>
                    )}
                    <Menu shadow="md" width={200} position="bottom-end">
                        <Menu.Target>
                            <ActionIcon variant="subtle" color="gray" aria-label={t`Actions`}>
                                <IconDotsVertical size={18}/>
                            </ActionIcon>
                        </Menu.Target>
                        <Menu.Dropdown>
                            <Menu.Item leftSection={<IconEye size={14}/>} onClick={() => handleViewMessage(message)}>
                                {t`View Message`}
                            </Menu.Item>
                        </Menu.Dropdown>
                    </Menu>
                </Group>
            ),
        },
    ], [approveMutation.isPending]);

    return (
        <Container size="xl" p="xl">
            <Stack gap="lg">
                <div>
                    <Title order={1}>{t`Outgoing Messages`}</Title>
                    <Text c="dimmed" size="sm">{t`View all messages sent across the platform`}</Text>
                </div>

                <TableCard>
                    <ToolBar
                        embedded
                        searchComponent={() => (
                            <TextInput
                                placeholder={t`Search by subject, event, or account...`}
                                leftSection={<IconSearch size={16}/>}
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                            />
                        )}
                        filterComponent={(
                            <>
                                <Select
                                    placeholder={t`Status`}
                                    clearable
                                    data={[
                                        {value: 'SENT', label: t`Sent`},
                                        {value: 'PENDING_REVIEW', label: t`Pending Review`},
                                        {value: 'SCHEDULED', label: t`Scheduled`},
                                        {value: 'CANCELLED', label: t`Cancelled`},
                                        {value: 'FAILED', label: t`Failed`},
                                    ]}
                                    value={statusFilter}
                                    onChange={setStatusFilter}
                                    w={150}
                                />
                                <Select
                                    placeholder={t`Type`}
                                    clearable
                                    data={[
                                        {value: 'TICKET', label: t`Ticket Holders`},
                                        {value: 'ORDER', label: t`Order Holders`},
                                        {value: 'ATTENDEE', label: t`Attendees`},
                                    ]}
                                    value={typeFilter}
                                    onChange={setTypeFilter}
                                    w={150}
                                />
                            </>
                        )}
                    />

                    {isLoading && <TableCardSkeleton/>}

                    {!isLoading && totalMessages === 0 && (
                        <div className={tableStyles.emptyState}>
                            <Text c="dimmed" size="lg">{t`No messages found`}</Text>
                        </div>
                    )}

                    {!isLoading && totalMessages > 0 && (
                        <TanStackTable data={messagesData?.data ?? []} columns={columns} noCard/>
                    )}

                    <TableCardFooter pagination={messagesData?.meta} page={page} onPageChange={setPage}/>
                </TableCard>
            </Stack>

            <Modal
                opened={detailModalOpened}
                onClose={closeDetailModal}
                title={t`Message Details`}
                size="lg"
            >
                {selectedMessage && (
                    <Stack gap="md">
                        <div>
                            <Text size="sm" fw={500} c="dimmed">{t`Subject`}</Text>
                            <Text fw={500}>{selectedMessage.subject}</Text>
                        </div>
                        <Group>
                            <div style={{flex: 1}}>
                                <Text size="sm" fw={500} c="dimmed">{t`Event`}</Text>
                                <Text size="sm">{selectedMessage.event_title}</Text>
                            </div>
                            <div style={{flex: 1}}>
                                <Text size="sm" fw={500} c="dimmed">{t`Account`}</Text>
                                <Text size="sm">{selectedMessage.account_name}</Text>
                            </div>
                        </Group>
                        <Group>
                            <div>
                                <Text size="sm" fw={500} c="dimmed">{t`Type`}</Text>
                                <Badge variant="light" color="gray">
                                    {getTypeLabel(selectedMessage.type)}
                                </Badge>
                            </div>
                            <div>
                                <Text size="sm" fw={500} c="dimmed">{t`Status`}</Text>
                                <Badge color={getStatusColor(selectedMessage.status)} variant="light">
                                    {selectedMessage.status}
                                </Badge>
                            </div>
                            <div>
                                <Text size="sm" fw={500} c="dimmed">{t`Recipients`}</Text>
                                <Text size="sm" fw={500}>{selectedMessage.recipients_count}</Text>
                            </div>
                        </Group>
                        <Group>
                            <div style={{flex: 1}}>
                                <Text size="sm" fw={500} c="dimmed">{t`Sent By`}</Text>
                                <Text size="sm">{selectedMessage.sent_by || '-'}</Text>
                            </div>
                            <div style={{flex: 1}}>
                                <Text size="sm" fw={500} c="dimmed">{t`Created`}</Text>
                                <Text size="sm">{selectedMessage.created_at}</Text>
                            </div>
                        </Group>
                        <div>
                            <Text size="sm" fw={500} c="dimmed" mb="xs">{t`Message Content`}</Text>
                            <ScrollArea h={300} style={{border: '1px solid var(--mantine-color-gray-3)', borderRadius: '4px'}}>
                                <div
                                    style={{padding: '12px'}}
                                    dangerouslySetInnerHTML={{__html: selectedMessage.message}}
                                />
                            </ScrollArea>
                        </div>
                        {selectedMessage.eligibility_failures && selectedMessage.eligibility_failures.length > 0 && (
                            <div>
                                <Text size="sm" fw={500} c="dimmed" mb="xs">{t`Eligibility Failures`}</Text>
                                <Group gap="xs">
                                    {selectedMessage.eligibility_failures.map((failure) => (
                                        <Badge key={failure} color="orange" variant="light">
                                            {getEligibilityFailureLabel(failure)}
                                        </Badge>
                                    ))}
                                </Group>
                            </div>
                        )}
                    </Stack>
                )}
            </Modal>
        </Container>
    );
};

export default Messages;
