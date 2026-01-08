import {Container, Title, TextInput, Skeleton, Pagination, Stack, Table, Text, Badge, Group, Select, Modal, ScrollArea, ActionIcon, Tooltip} from "@mantine/core";
import {t} from "@lingui/macro";
import {IconSearch, IconMail, IconUsers, IconEye, IconCheck} from "@tabler/icons-react";
import {useState, useEffect} from "react";
import {useGetAdminMessages} from "../../../../queries/useGetAdminMessages";
import {useApproveMessage} from "../../../../mutations/useApproveMessage";
import {relativeDate} from "../../../../utilites/dates";
import {useDisclosure} from "@mantine/hooks";
import {AdminMessage} from "../../../../api/admin.client";
import {showSuccess} from "../../../../utilites/notifications";
import {IdParam} from "../../../../types";
import tableStyles from "../../../../styles/admin-table.module.scss";

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

    return (
        <Container size="xl" p="xl">
            <Stack gap="lg">
                <div>
                    <Title order={1}>{t`Outgoing Messages`}</Title>
                    <Text c="dimmed" size="sm">{t`View all messages sent across the platform`}</Text>
                </div>

                <Group>
                    <TextInput
                        placeholder={t`Search by subject, event, or account...`}
                        leftSection={<IconSearch size={16} />}
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        style={{flex: 1}}
                    />
                    <Select
                        placeholder={t`Status`}
                        clearable
                        data={[
                            {value: 'SENT', label: t`Sent`},
                            {value: 'PENDING_REVIEW', label: t`Pending Review`},
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
                </Group>

                {isLoading ? (
                    <Stack gap="md">
                        <Skeleton height={50} radius="md" />
                        <Skeleton height={400} radius="md" />
                    </Stack>
                ) : totalMessages === 0 ? (
                    <div className={tableStyles.emptyState}>
                        <Text c="dimmed" size="lg">{t`No messages found`}</Text>
                    </div>
                ) : (
                    <div className={tableStyles.tableWrapper}>
                        <div className={tableStyles.tableScroll}>
                            <Table className={tableStyles.table} highlightOnHover>
                                <Table.Thead>
                                    <Table.Tr>
                                        <Table.Th>{t`Subject`}</Table.Th>
                                        <Table.Th>{t`Event`}</Table.Th>
                                        <Table.Th>{t`Account`}</Table.Th>
                                        <Table.Th>{t`Type`}</Table.Th>
                                        <Table.Th>{t`Recipients`}</Table.Th>
                                        <Table.Th>{t`Status`}</Table.Th>
                                        <Table.Th>{t`Sent By`}</Table.Th>
                                        <Table.Th>{t`Created`}</Table.Th>
                                        <Table.Th style={{width: 60}}></Table.Th>
                                    </Table.Tr>
                                </Table.Thead>
                                <Table.Tbody>
                                    {messagesData?.data?.map((message) => (
                                        <Table.Tr key={message.id}>
                                            <Table.Td>
                                                <Group gap="xs">
                                                    <IconMail size={16} color="gray" />
                                                    <Text size="sm" fw={500} lineClamp={1} maw={200}>
                                                        {message.subject}
                                                    </Text>
                                                </Group>
                                            </Table.Td>
                                            <Table.Td>
                                                <Text size="sm" lineClamp={1} maw={150}>
                                                    {message.event_title}
                                                </Text>
                                            </Table.Td>
                                            <Table.Td>
                                                <Text size="sm" c="dimmed" lineClamp={1} maw={120}>
                                                    {message.account_name}
                                                </Text>
                                            </Table.Td>
                                            <Table.Td>
                                                <Badge variant="light" color="gray">
                                                    {getTypeLabel(message.type)}
                                                </Badge>
                                            </Table.Td>
                                            <Table.Td>
                                                <Group gap={4}>
                                                    <IconUsers size={14} color="gray" />
                                                    <Text size="sm" fw={500}>
                                                        {message.recipients_count}
                                                    </Text>
                                                </Group>
                                            </Table.Td>
                                            <Table.Td>
                                                <Badge color={getStatusColor(message.status)} variant="light">
                                                    {message.status}
                                                </Badge>
                                            </Table.Td>
                                            <Table.Td>
                                                <Text size="sm" c="dimmed">
                                                    {message.sent_by || '-'}
                                                </Text>
                                            </Table.Td>
                                            <Table.Td>
                                                <Text size="sm">{relativeDate(message.created_at)}</Text>
                                            </Table.Td>
                                            <Table.Td>
                                                <Group gap="xs">
                                                    <Tooltip label={t`View Message`}>
                                                        <ActionIcon
                                                            variant="subtle"
                                                            color="gray"
                                                            onClick={() => handleViewMessage(message)}
                                                        >
                                                            <IconEye size={16} />
                                                        </ActionIcon>
                                                    </Tooltip>
                                                    {message.status === 'PENDING_REVIEW' && (
                                                        <Tooltip label={t`Approve Message`}>
                                                            <ActionIcon
                                                                variant="subtle"
                                                                color="green"
                                                                onClick={() => handleApprove(message.id)}
                                                                loading={approveMutation.isPending}
                                                            >
                                                                <IconCheck size={16} />
                                                            </ActionIcon>
                                                        </Tooltip>
                                                    )}
                                                </Group>
                                            </Table.Td>
                                        </Table.Tr>
                                    ))}
                                </Table.Tbody>
                            </Table>
                        </div>
                    </div>
                )}

                {messagesData?.meta && messagesData.meta.last_page > 1 && (
                    <Pagination
                        total={messagesData.meta.last_page}
                        value={page}
                        onChange={setPage}
                        mt="md"
                    />
                )}
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
