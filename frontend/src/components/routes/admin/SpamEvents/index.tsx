import {
    ActionIcon,
    Badge,
    Button,
    Container,
    Group,
    Menu,
    Modal,
    Pagination,
    ScrollArea,
    Skeleton,
    Stack,
    Table,
    Text,
    TextInput,
    Title,
    Tooltip
} from "@mantine/core";
import {t} from "@lingui/macro";
import {
    IconBan,
    IconCalendarEvent,
    IconCheck,
    IconDotsVertical,
    IconExternalLink,
    IconEye,
    IconSearch,
    IconTrash,
    IconUserX
} from "@tabler/icons-react";
import {useEffect, useState} from "react";
import {useDisclosure} from "@mantine/hooks";
import {Link} from "react-router";
import {useGetAdminSpamEvents} from "../../../../queries/useGetAdminSpamEvents";
import {useApproveSpamEvent} from "../../../../mutations/useApproveSpamEvent";
import {useConfirmSpamEvent} from "../../../../mutations/useConfirmSpamEvent";
import {useDeleteSpamEvent} from "../../../../mutations/useDeleteSpamEvent";
import {eventHomepageUrl} from "../../../../utilites/urlHelper";
import {relativeDate} from "../../../../utilites/dates";
import {AdminSpamEvent} from "../../../../api/admin.client";
import {confirmationDialog} from "../../../../utilites/confirmationDialog";
import {showError, showSuccess} from "../../../../utilites/notifications";
import {Event, IdParam} from "../../../../types";
import tableStyles from "../../../../styles/admin-table.module.scss";

const SpamEvents = () => {
    const [page, setPage] = useState(1);
    const [search, setSearch] = useState("");
    const [debouncedSearch, setDebouncedSearch] = useState("");
    const [selectedSpamEvent, setSelectedSpamEvent] = useState<AdminSpamEvent | null>(null);
    const [actingEventId, setActingEventId] = useState<IdParam | null>(null);
    const [detailModalOpened, {open: openDetailModal, close: closeDetailModal}] = useDisclosure(false);

    const {data: spamEventsData, isLoading} = useGetAdminSpamEvents({
        page,
        per_page: 20,
        search: debouncedSearch,
    });

    const approveMutation = useApproveSpamEvent();
    const confirmSpamMutation = useConfirmSpamEvent();
    const deleteSpamEventMutation = useDeleteSpamEvent();

    useEffect(() => {
        const timer = setTimeout(() => {
            setDebouncedSearch(search);
            setPage(1);
        }, 500);

        return () => clearTimeout(timer);
    }, [search]);

    const isActing = approveMutation.isPending || confirmSpamMutation.isPending || deleteSpamEventMutation.isPending;

    const handleApprove = (eventId: IdParam) => {
        confirmationDialog(t`Are you sure this event is not spam? It will be published immediately.`, () => {
            setActingEventId(eventId);
            approveMutation.mutate(eventId, {
                onSuccess: () => {
                    showSuccess(t`Event approved and published`);
                    closeDetailModal();
                },
                onError: () => showError(t`Failed to approve event`),
                onSettled: () => setActingEventId(null),
            });
        });
    };

    const handleConfirmSpam = (eventId: IdParam) => {
        confirmationDialog(t`Are you sure you want to confirm this event as spam? It will remain unpublished.`, () => {
            setActingEventId(eventId);
            confirmSpamMutation.mutate(eventId, {
                onSuccess: () => {
                    showSuccess(t`Event confirmed as spam`);
                    closeDetailModal();
                },
                onError: () => showError(t`Failed to confirm event as spam`),
                onSettled: () => setActingEventId(null),
            });
        });
    };

    const handleDelete = (spamEvent: AdminSpamEvent, deleteAccount: boolean) => {
        const accountLabel = spamEvent.account_email || spamEvent.account_name || '';
        const message = deleteAccount
            ? t`Delete this event and immediately delete the account ${accountLabel}? This cannot be undone.`
            : t`Delete this event? It will be marked as spam and removed.`;

        confirmationDialog(message, () => {
            setActingEventId(spamEvent.event_id);
            deleteSpamEventMutation.mutate({eventId: spamEvent.event_id, deleteAccount}, {
                onSuccess: () => {
                    showSuccess(deleteAccount ? t`Event deleted and account deletion queued` : t`Event deleted`);
                    closeDetailModal();
                },
                onError: (error: any) => showError(error?.response?.data?.message ?? t`Failed to delete event`),
                onSettled: () => setActingEventId(null),
            });
        }, {confirm: deleteAccount ? t`Delete event & account` : t`Delete event`, destructive: true});
    };

    const eventPageUrl = (spamEvent: AdminSpamEvent) =>
        eventHomepageUrl({id: spamEvent.event_id, slug: spamEvent.event_slug} as Event);

    const handleViewSpamEvent = (spamEvent: AdminSpamEvent) => {
        setSelectedSpamEvent(spamEvent);
        openDetailModal();
    };

    const formatConfidence = (confidence: number) => `${Math.round(confidence * 100)}%`;

    const totalSpamEvents = spamEventsData?.meta?.total || 0;

    return (
        <Container size="xl" p="xl">
            <Stack gap="lg">
                <div>
                    <Title order={1}>{t`Flagged Events`}</Title>
                    <Text c="dimmed" size="sm">{t`Review events flagged as potential spam by the automated check`}</Text>
                </div>

                <TextInput
                    placeholder={t`Search by event, organizer, or account email...`}
                    leftSection={<IconSearch size={16}/>}
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    data-testid="spam-events-search-input"
                />

                {isLoading ? (
                    <Stack gap="md">
                        <Skeleton height={50} radius="md"/>
                        <Skeleton height={400} radius="md"/>
                    </Stack>
                ) : totalSpamEvents === 0 ? (
                    <div className={tableStyles.emptyState}>
                        <Text c="dimmed" size="lg">{t`No flagged events awaiting review`}</Text>
                    </div>
                ) : (
                    <div className={tableStyles.tableWrapper}>
                        <div className={tableStyles.tableScroll}>
                            <Table className={tableStyles.table} highlightOnHover>
                                <Table.Thead>
                                    <Table.Tr>
                                        <Table.Th>{t`Event`}</Table.Th>
                                        <Table.Th>{t`Organizer`}</Table.Th>
                                        <Table.Th>{t`Account`}</Table.Th>
                                        <Table.Th>{t`Confidence`}</Table.Th>
                                        <Table.Th>{t`Checked`}</Table.Th>
                                        <Table.Th></Table.Th>
                                    </Table.Tr>
                                </Table.Thead>
                                <Table.Tbody>
                                    {spamEventsData?.data?.map((spamEvent) => (
                                        <Table.Tr key={spamEvent.id}>
                                            <Table.Td>
                                                <Group gap="xs">
                                                    <IconCalendarEvent size={16} color="gray"/>
                                                    <Text size="sm" fw={500} lineClamp={1} maw={250}>
                                                        {spamEvent.event_title}
                                                    </Text>
                                                </Group>
                                            </Table.Td>
                                            <Table.Td>
                                                <Text size="sm" lineClamp={1} maw={150}>
                                                    {spamEvent.organizer_name || '-'}
                                                </Text>
                                            </Table.Td>
                                            <Table.Td>
                                                <Text size="sm" c="dimmed" lineClamp={1} maw={180}>
                                                    {spamEvent.account_email || spamEvent.account_name || '-'}
                                                </Text>
                                            </Table.Td>
                                            <Table.Td>
                                                <Badge color="orange" variant="light">
                                                    {formatConfidence(spamEvent.verdict?.confidence || 0)}
                                                </Badge>
                                            </Table.Td>
                                            <Table.Td>
                                                <Text size="sm">{relativeDate(spamEvent.checked_at)}</Text>
                                            </Table.Td>
                                            <Table.Td>
                                                <Group gap={4} wrap="nowrap">
                                                    <Tooltip label={t`View Details`}>
                                                        <ActionIcon
                                                            variant="subtle"
                                                            color="gray"
                                                            data-testid="view-spam-event-button"
                                                            onClick={() => handleViewSpamEvent(spamEvent)}
                                                        >
                                                            <IconEye size={16}/>
                                                        </ActionIcon>
                                                    </Tooltip>
                                                    <Tooltip label={t`Approve - Not Spam`}>
                                                        <ActionIcon
                                                            variant="subtle"
                                                            color="green"
                                                            data-testid="approve-spam-event-button"
                                                            onClick={() => handleApprove(spamEvent.event_id)}
                                                            loading={approveMutation.isPending && actingEventId === spamEvent.event_id}
                                                            disabled={isActing && actingEventId !== spamEvent.event_id}
                                                        >
                                                            <IconCheck size={16}/>
                                                        </ActionIcon>
                                                    </Tooltip>
                                                    <Tooltip label={t`Confirm Spam`}>
                                                        <ActionIcon
                                                            variant="subtle"
                                                            color="red"
                                                            data-testid="confirm-spam-event-button"
                                                            onClick={() => handleConfirmSpam(spamEvent.event_id)}
                                                            loading={confirmSpamMutation.isPending && actingEventId === spamEvent.event_id}
                                                            disabled={isActing && actingEventId !== spamEvent.event_id}
                                                        >
                                                            <IconBan size={16}/>
                                                        </ActionIcon>
                                                    </Tooltip>
                                                    <Tooltip label={t`Open event page`}>
                                                        <ActionIcon
                                                            variant="subtle"
                                                            color="gray"
                                                            component="a"
                                                            href={eventPageUrl(spamEvent)}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            data-testid="open-spam-event-page-button"
                                                        >
                                                            <IconExternalLink size={16}/>
                                                        </ActionIcon>
                                                    </Tooltip>
                                                    <Menu position="bottom-end" withinPortal>
                                                        <Menu.Target>
                                                            <ActionIcon
                                                                variant="subtle"
                                                                color="gray"
                                                                aria-label={t`More actions`}
                                                                data-testid="spam-event-more-actions-button"
                                                                loading={deleteSpamEventMutation.isPending && actingEventId === spamEvent.event_id}
                                                                disabled={isActing && actingEventId !== spamEvent.event_id}
                                                            >
                                                                <IconDotsVertical size={16}/>
                                                            </ActionIcon>
                                                        </Menu.Target>
                                                        <Menu.Dropdown>
                                                            <Menu.Item
                                                                color="red"
                                                                leftSection={<IconTrash size={14}/>}
                                                                onClick={() => handleDelete(spamEvent, false)}
                                                                data-testid="delete-spam-event-menu-item"
                                                            >
                                                                {t`Delete event`}
                                                            </Menu.Item>
                                                            <Menu.Item
                                                                color="red"
                                                                leftSection={<IconUserX size={14}/>}
                                                                onClick={() => handleDelete(spamEvent, true)}
                                                                data-testid="delete-spam-event-and-account-menu-item"
                                                            >
                                                                {t`Delete event & account`}
                                                            </Menu.Item>
                                                        </Menu.Dropdown>
                                                    </Menu>
                                                </Group>
                                            </Table.Td>
                                        </Table.Tr>
                                    ))}
                                </Table.Tbody>
                            </Table>
                        </div>
                    </div>
                )}

                {spamEventsData?.meta && spamEventsData.meta.last_page > 1 && (
                    <Pagination
                        total={spamEventsData.meta.last_page}
                        value={page}
                        onChange={setPage}
                        mt="md"
                    />
                )}
            </Stack>

            <Modal
                opened={detailModalOpened}
                onClose={closeDetailModal}
                title={t`Flagged Event Details`}
                size="lg"
            >
                {selectedSpamEvent && (
                    <Stack gap="md">
                        <div>
                            <Text size="sm" fw={500} c="dimmed">{t`Event`}</Text>
                            <Text fw={500}>{selectedSpamEvent.event_title}</Text>
                        </div>
                        {selectedSpamEvent.event_description && (
                            <div>
                                <Text size="sm" fw={500} c="dimmed">{t`Description`}</Text>
                                <ScrollArea.Autosize mah={200}>
                                    <Text size="sm" style={{whiteSpace: 'pre-wrap'}}>
                                        {selectedSpamEvent.event_description}
                                    </Text>
                                </ScrollArea.Autosize>
                            </div>
                        )}
                        <Group>
                            <div style={{flex: 1}}>
                                <Text size="sm" fw={500} c="dimmed">{t`Organizer`}</Text>
                                <Text size="sm">{selectedSpamEvent.organizer_name || '-'}</Text>
                            </div>
                            <div style={{flex: 1}}>
                                <Text size="sm" fw={500} c="dimmed">{t`Account`}</Text>
                                <Text size="sm" component={Link} to={`/admin/accounts/${selectedSpamEvent.account_id}`}>
                                    {selectedSpamEvent.account_name || '-'}
                                </Text>
                                <Text size="sm" c="dimmed">{selectedSpamEvent.account_email}</Text>
                            </div>
                        </Group>
                        <div>
                            <Text size="sm" fw={500} c="dimmed">{t`Confidence`}</Text>
                            <Badge color="orange" variant="light">
                                {formatConfidence(selectedSpamEvent.verdict?.confidence || 0)}
                            </Badge>
                        </div>
                        {selectedSpamEvent.verdict?.reasons && selectedSpamEvent.verdict.reasons.length > 0 && (
                            <div>
                                <Text size="sm" fw={500} c="dimmed" mb="xs">{t`Reasons`}</Text>
                                <Stack gap="xs">
                                    {selectedSpamEvent.verdict.reasons.map((reason) => (
                                        <Badge key={reason} color="orange" variant="light" style={{textTransform: 'none'}}>
                                            {reason}
                                        </Badge>
                                    ))}
                                </Stack>
                            </div>
                        )}
                        <Group justify="space-between" mt="sm">
                            <Button
                                variant="subtle"
                                component="a"
                                href={eventPageUrl(selectedSpamEvent)}
                                target="_blank"
                                rel="noopener noreferrer"
                                leftSection={<IconExternalLink size={14}/>}
                            >
                                {t`Open event page`}
                            </Button>
                            <Group gap="xs">
                                <Button variant="light" color="red" onClick={() => handleDelete(selectedSpamEvent, false)}>
                                    {t`Delete event`}
                                </Button>
                                <Button color="red" onClick={() => handleDelete(selectedSpamEvent, true)}>
                                    {t`Delete event & account`}
                                </Button>
                            </Group>
                        </Group>
                    </Stack>
                )}
            </Modal>
        </Container>
    );
};

export default SpamEvents;
