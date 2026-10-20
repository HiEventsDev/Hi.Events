import {Badge, Button, Container, Group, Modal, Pagination, SegmentedControl, Skeleton, Stack, Table, Text, TextInput, Title} from "@mantine/core";
import {t} from "@lingui/macro";
import {IconSearch} from "@tabler/icons-react";
import {useEffect, useState} from "react";
import {useNavigate} from "react-router";
import {useGetAllDeletionRequests} from "../../../../queries/useGetAllDeletionRequests";
import {useAdminCancelDeletionRequest} from "../../../../mutations/useAdminCancelDeletionRequest";
import {useAdminExecuteDeletionRequest} from "../../../../mutations/useAdminExecuteDeletionRequest";
import {AdminDeletionRequest} from "../../../../api/admin.client";
import {showError, showSuccess} from "../../../../utilites/notifications";
import tableStyles from "../../../../styles/admin-table.module.scss";

const statusColors: Record<AdminDeletionRequest['status'], string> = {
    REQUESTED: 'orange',
    CANCELLED: 'gray',
    COMPLETED: 'red',
};

const DeletionRequests = () => {
    const navigate = useNavigate();
    const [page, setPage] = useState(1);
    const [search, setSearch] = useState("");
    const [debouncedSearch, setDebouncedSearch] = useState("");
    const [status, setStatus] = useState("REQUESTED");
    const [confirmAction, setConfirmAction] = useState<{ type: 'cancel' | 'execute'; request: AdminDeletionRequest } | null>(null);

    const {data: requestsData, isLoading} = useGetAllDeletionRequests({
        page,
        per_page: 20,
        search: debouncedSearch,
        status: status === 'ALL' ? undefined : status,
    });

    const cancelMutation = useAdminCancelDeletionRequest();
    const executeMutation = useAdminExecuteDeletionRequest();

    useEffect(() => {
        const timer = setTimeout(() => {
            setDebouncedSearch(search);
            setPage(1);
        }, 500);

        return () => clearTimeout(timer);
    }, [search]);

    const formatDate = (dateString?: string | null) => dateString ? new Date(dateString).toLocaleDateString() : '-';

    const handleConfirm = () => {
        if (!confirmAction) {
            return;
        }

        const mutation = confirmAction.type === 'cancel' ? cancelMutation : executeMutation;
        const successMessage = confirmAction.type === 'cancel'
            ? t`Deletion request cancelled`
            : t`Deletion queued for execution`;

        mutation.mutate(confirmAction.request.id, {
            onSuccess: () => {
                showSuccess(successMessage);
                setConfirmAction(null);
            },
            onError: (error: any) => {
                showError(error?.response?.data?.message || t`Something went wrong. Please try again.`);
                setConfirmAction(null);
            }
        });
    };

    const requests = requestsData?.data || [];

    return (
        <Container size="xl" p="xl">
            <Stack gap="lg">
                <Title order={1}>{t`Deletion Requests`}</Title>

                <Group>
                    <SegmentedControl
                        value={status}
                        onChange={(value) => {
                            setStatus(value);
                            setPage(1);
                        }}
                        data={[
                            {label: t`Pending`, value: 'REQUESTED'},
                            {label: t`Completed`, value: 'COMPLETED'},
                            {label: t`Cancelled`, value: 'CANCELLED'},
                            {label: t`All`, value: 'ALL'},
                        ]}
                    />
                    <TextInput
                        style={{flex: 1}}
                        placeholder={t`Search by account name or email...`}
                        leftSection={<IconSearch size={16}/>}
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />
                </Group>

                {isLoading && (
                    <Stack gap="md">
                        <Skeleton height={120} radius="md"/>
                        <Skeleton height={120} radius="md"/>
                    </Stack>
                )}

                {!isLoading && requests.length === 0 && (
                    <Text size="lg" c="dimmed" ta="center" py="xl">{t`No deletion requests found`}</Text>
                )}

                {!isLoading && requests.length > 0 && (
                    <div className={tableStyles.tableWrapper}>
                        <div className={tableStyles.tableScroll}>
                            <Table className={tableStyles.table}>
                                <Table.Thead>
                                    <Table.Tr>
                                        <Table.Th>{t`Account`}</Table.Th>
                                        <Table.Th>{t`Requested by`}</Table.Th>
                                        <Table.Th>{t`Requested`}</Table.Th>
                                        <Table.Th>{t`Scheduled deletion`}</Table.Th>
                                        <Table.Th>{t`Outcome`}</Table.Th>
                                        <Table.Th>{t`Status`}</Table.Th>
                                        <Table.Th></Table.Th>
                                    </Table.Tr>
                                </Table.Thead>
                                <Table.Tbody>
                                    {requests.map((request) => (
                                        <Table.Tr key={String(request.id)}>
                                            <Table.Td>
                                                {request.account ? (
                                                    <Stack gap={2}>
                                                        <Text
                                                            size="sm"
                                                            fw={500}
                                                            style={{cursor: 'pointer'}}
                                                            onClick={() => navigate(`/admin/accounts/${request.account?.id}`)}
                                                        >
                                                            {request.account.name}
                                                        </Text>
                                                        <Text size="xs" c="dimmed">{request.account.email}</Text>
                                                    </Stack>
                                                ) : (
                                                    <Text size="sm" c="dimmed">{t`Deleted account`}</Text>
                                                )}
                                            </Table.Td>
                                            <Table.Td>
                                                <Stack gap={2}>
                                                    <Text size="sm">{request.requested_by_user?.full_name || '-'}</Text>
                                                    <Badge size="xs" variant="light" color={request.initiated_by === 'ADMIN' ? 'grape' : 'blue'}>
                                                        {request.initiated_by === 'ADMIN' ? t`Admin` : t`Account owner`}
                                                    </Badge>
                                                </Stack>
                                            </Table.Td>
                                            <Table.Td>{formatDate(request.requested_at)}</Table.Td>
                                            <Table.Td>{formatDate(request.scheduled_deletion_at)}</Table.Td>
                                            <Table.Td>
                                                <Badge
                                                    variant="light"
                                                    color={(request.outcome || request.expected_outcome) === 'HARD_DELETE' ? 'red' : 'yellow'}
                                                >
                                                    {(request.outcome || request.expected_outcome) === 'HARD_DELETE' ? t`Hard delete` : t`Anonymize`}
                                                </Badge>
                                            </Table.Td>
                                            <Table.Td>
                                                <Badge variant="light" color={statusColors[request.status]}>
                                                    {request.status}
                                                </Badge>
                                            </Table.Td>
                                            <Table.Td>
                                                {request.status === 'REQUESTED' && (
                                                    <Group gap="xs" wrap="nowrap">
                                                        <Button
                                                            size="xs"
                                                            variant="light"
                                                            onClick={() => setConfirmAction({type: 'cancel', request})}
                                                            data-testid="admin-cancel-deletion-button"
                                                        >
                                                            {t`Cancel`}
                                                        </Button>
                                                        <Button
                                                            size="xs"
                                                            color="red"
                                                            variant="light"
                                                            onClick={() => setConfirmAction({type: 'execute', request})}
                                                            data-testid="admin-execute-deletion-button"
                                                        >
                                                            {t`Execute now`}
                                                        </Button>
                                                    </Group>
                                                )}
                                            </Table.Td>
                                        </Table.Tr>
                                    ))}
                                </Table.Tbody>
                            </Table>
                        </div>
                    </div>
                )}

                {requestsData?.meta && requestsData.meta.last_page > 1 && (
                    <Pagination
                        total={requestsData.meta.last_page}
                        value={page}
                        onChange={setPage}
                        mt="md"
                    />
                )}
            </Stack>

            <Modal
                opened={confirmAction !== null}
                onClose={() => setConfirmAction(null)}
                title={confirmAction?.type === 'cancel' ? t`Cancel deletion request` : t`Execute deletion now`}
            >
                <Stack>
                    <Text size="sm">
                        {confirmAction?.type === 'cancel'
                            ? t`This will cancel the scheduled deletion and reactivate the account. The account owner will be notified by email.`
                            : t`This will permanently delete or anonymize the account immediately, without waiting for the scheduled date. This cannot be undone.`}
                    </Text>
                    <Text size="sm" fw={500}>{confirmAction?.request.account?.name}</Text>
                    <Group justify="flex-end">
                        <Button variant="default" onClick={() => setConfirmAction(null)}>
                            {t`Go back`}
                        </Button>
                        <Button
                            color={confirmAction?.type === 'execute' ? 'red' : 'blue'}
                            loading={cancelMutation.isPending || executeMutation.isPending}
                            onClick={handleConfirm}
                            data-testid="admin-confirm-deletion-action-button"
                        >
                            {confirmAction?.type === 'cancel' ? t`Cancel deletion` : t`Execute deletion`}
                        </Button>
                    </Group>
                </Stack>
            </Modal>
        </Container>
    );
};

export default DeletionRequests;
