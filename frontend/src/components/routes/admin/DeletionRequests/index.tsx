import {Button, Container, Group, Modal, SegmentedControl, Stack, Text, TextInput, Title, VisuallyHidden} from "@mantine/core";
import {t} from "@lingui/macro";
import {IconSearch} from "@tabler/icons-react";
import {useEffect, useMemo, useState} from "react";
import {useNavigate} from "react-router";
import {useGetAllDeletionRequests} from "../../../../queries/useGetAllDeletionRequests";
import {useAdminCancelDeletionRequest} from "../../../../mutations/useAdminCancelDeletionRequest";
import {useAdminExecuteDeletionRequest} from "../../../../mutations/useAdminExecuteDeletionRequest";
import {AdminDeletionRequest} from "../../../../api/admin.client";
import {showError, showSuccess} from "../../../../utilites/notifications";
import {TanStackTable, TanStackTableColumn} from "../../../common/TanStackTable";
import {CellLink, CellText, EmptyCell, IdentityCell} from "../../../common/TanStackTable/cells.tsx";
import {StatusBadge, StatusTone} from "../../../common/StatusBadge";
import {TableCard, TableCardFooter, TableCardSkeleton} from "../../../common/TableCard";
import {ToolBar} from "../../../common/ToolBar";
import tableStyles from "../../../../styles/admin-table.module.scss";

const statusTones: Record<AdminDeletionRequest['status'], StatusTone> = {
    REQUESTED: 'waiting',
    CANCELLED: 'neutral',
    COMPLETED: 'negative',
};

const formatDate = (dateString?: string | null) => dateString ? new Date(dateString).toLocaleDateString() : <EmptyCell/>;

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

    const columns = useMemo<TanStackTableColumn<AdminDeletionRequest>[]>(() => [
        {
            id: 'account',
            header: t`Account`,
            meta: {sticky: 'left'},
            cell: ({row: {original: request}}) => {
                const account = request.account;
                if (!account) {
                    return <CellText muted>{t`Deleted account`}</CellText>;
                }

                return (
                    <IdentityCell
                        primary={(
                            <CellLink strong title={account.name} onClick={() => navigate(`/admin/accounts/${account.id}`)}>
                                {account.name}
                            </CellLink>
                        )}
                        secondary={account.email}
                    />
                );
            },
        },
        {
            id: 'requested_by',
            header: t`Requested by`,
            cell: ({row: {original: request}}) => (
                <IdentityCell
                    primary={request.requested_by_user?.full_name || <EmptyCell/>}
                    trailing={(
                        <StatusBadge tone={request.initiated_by === 'ADMIN' ? 'active' : 'neutral'}>
                            {request.initiated_by === 'ADMIN' ? t`Admin` : t`Account owner`}
                        </StatusBadge>
                    )}
                />
            ),
        },
        {
            id: 'requested',
            header: t`Requested`,
            cell: ({row: {original: request}}) => formatDate(request.requested_at),
        },
        {
            id: 'scheduled',
            header: t`Scheduled deletion`,
            cell: ({row: {original: request}}) => formatDate(request.scheduled_deletion_at),
        },
        {
            id: 'outcome',
            header: t`Outcome`,
            cell: ({row: {original: request}}) => (request.outcome || request.expected_outcome) === 'HARD_DELETE'
                ? <StatusBadge tone="negative">{t`Hard delete`}</StatusBadge>
                : <StatusBadge tone="waiting">{t`Anonymize`}</StatusBadge>,
        },
        {
            id: 'status',
            header: t`Status`,
            cell: ({row: {original: request}}) => (
                <StatusBadge tone={statusTones[request.status]}>{request.status}</StatusBadge>
            ),
        },
        {
            id: 'actions',
            header: () => <VisuallyHidden>{t`Actions`}</VisuallyHidden>,
            meta: {sticky: 'right'},
            cell: ({row: {original: request}}) => request.status === 'REQUESTED' && (
                <Group gap="xs" wrap="nowrap" justify="flex-end">
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
            ),
        },
    ], []);

    return (
        <Container size="xl" p="xl">
            <Stack gap="lg">
                <Title order={1}>{t`Deletion Requests`}</Title>

                <TableCard>
                    <ToolBar
                        embedded
                        searchComponent={() => (
                            <TextInput
                                placeholder={t`Search by account name or email...`}
                                leftSection={<IconSearch size={16}/>}
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                            />
                        )}
                        filterComponent={(
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
                        )}
                    />

                    {isLoading && <TableCardSkeleton/>}

                    {!isLoading && requests.length === 0 && (
                        <div className={tableStyles.emptyState}>
                            <Text size="lg" c="dimmed">{t`No deletion requests found`}</Text>
                        </div>
                    )}

                    {!isLoading && requests.length > 0 && (
                        <TanStackTable data={requests} columns={columns} noCard/>
                    )}

                    <TableCardFooter pagination={requestsData?.meta} page={page} onPageChange={setPage}/>
                </TableCard>
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
