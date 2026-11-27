import {Container, Title, TextInput, Stack, Button, Group, Text, Badge, Menu, Modal, Code, ScrollArea, ActionIcon, VisuallyHidden} from "@mantine/core";
import {t} from "@lingui/macro";
import {IconDotsVertical, IconSearch, IconTrash, IconRefresh, IconEye} from "@tabler/icons-react";
import {useState, useEffect, useMemo} from "react";
import {useGetAdminFailedJobs} from "../../../../queries/useGetAdminFailedJobs";
import {useDeleteFailedJob} from "../../../../mutations/useDeleteFailedJob";
import {useDeleteAllFailedJobs} from "../../../../mutations/useDeleteAllFailedJobs";
import {useRetryFailedJob} from "../../../../mutations/useRetryFailedJob";
import {useRetryAllFailedJobs} from "../../../../mutations/useRetryAllFailedJobs";
import {showError, showSuccess} from "../../../../utilites/notifications";
import {AdminFailedJob} from "../../../../api/admin.client";
import {useDisclosure} from "@mantine/hooks";
import {relativeDate} from "../../../../utilites/dates";
import {TanStackTable, TanStackTableColumn} from "../../../common/TanStackTable";
import {CellCopy, CellText} from "../../../common/TanStackTable/cells.tsx";
import {TableCard, TableCardFooter, TableCardSkeleton} from "../../../common/TableCard";
import {ToolBar} from "../../../common/ToolBar";
import tableStyles from "../../../../styles/admin-table.module.scss";
import {IdParam} from "../../../../types";

const FailedJobs = () => {
    const [page, setPage] = useState(1);
    const [search, setSearch] = useState("");
    const [debouncedSearch, setDebouncedSearch] = useState("");
    const [selectedJob, setSelectedJob] = useState<AdminFailedJob | null>(null);
    const [detailModalOpened, {open: openDetailModal, close: closeDetailModal}] = useDisclosure(false);

    const {data: jobsData, isLoading} = useGetAdminFailedJobs({
        page,
        per_page: 20,
        search: debouncedSearch,
    });

    const deleteJobMutation = useDeleteFailedJob();
    const deleteAllJobsMutation = useDeleteAllFailedJobs();
    const retryJobMutation = useRetryFailedJob();
    const retryAllJobsMutation = useRetryAllFailedJobs();

    useEffect(() => {
        const timer = setTimeout(() => {
            setDebouncedSearch(search);
            setPage(1);
        }, 500);

        return () => clearTimeout(timer);
    }, [search]);

    const handleViewDetails = (job: AdminFailedJob) => {
        setSelectedJob(job);
        openDetailModal();
    };

    const handleRetryJob = (jobId: IdParam) => {
        if (!jobId) return;
        retryJobMutation.mutate(jobId, {
            onSuccess: () => {
                showSuccess(t`Job queued for retry`);
            },
            onError: () => {
                showError(t`Failed to retry job`);
            }
        });
    };

    const handleDeleteJob = (jobId: IdParam) => {
        if (!jobId) return;
        deleteJobMutation.mutate(jobId, {
            onSuccess: () => {
                showSuccess(t`Job deleted`);
            },
            onError: () => {
                showError(t`Failed to delete job`);
            }
        });
    };

    const handleRetryAll = () => {
        retryAllJobsMutation.mutate(undefined, {
            onSuccess: (response: any) => {
                showSuccess(response.message || t`All jobs queued for retry`);
            },
            onError: () => {
                showError(t`Failed to retry jobs`);
            }
        });
    };

    const handleDeleteAll = () => {
        if (!confirm(t`Are you sure you want to delete all failed jobs?`)) {
            return;
        }
        deleteAllJobsMutation.mutate(undefined, {
            onSuccess: () => {
                showSuccess(t`All failed jobs deleted`);
            },
            onError: () => {
                showError(t`Failed to delete jobs`);
            }
        });
    };

    const formatPayloadForDisplay = (payload: string | undefined) => {
        if (!payload) return 'No payload data';
        try {
            return JSON.stringify(JSON.parse(payload), null, 2);
        } catch {
            return payload;
        }
    };

    const totalJobs = jobsData?.meta?.total || 0;

    const columns = useMemo<TanStackTableColumn<AdminFailedJob>[]>(() => [
        {
            id: 'job',
            header: t`Job`,
            meta: {sticky: 'left'},
            cell: ({row: {original: job}}) => <CellText strong title={job.job_name}>{job.job_name}</CellText>,
        },
        {
            id: 'uuid',
            header: t`UUID`,
            cell: ({row: {original: job}}) => (
                <CellCopy value={job.uuid}>
                    <CellText mono muted>{job.uuid}</CellText>
                </CellCopy>
            ),
        },
        {
            id: 'queue',
            header: t`Queue`,
            cell: ({row: {original: job}}) => <CellText mono>{job.queue}</CellText>,
        },
        {
            id: 'failed_at',
            header: t`Failed At`,
            cell: ({row: {original: job}}) => <span title={job.failed_at}>{relativeDate(job.failed_at)}</span>,
        },
        {
            id: 'exception',
            header: t`Exception`,
            cell: ({row: {original: job}}) => (
                <CellText maxWidth={300} title={job.exception_summary}>{job.exception_summary}</CellText>
            ),
        },
        {
            id: 'actions',
            header: () => <VisuallyHidden>{t`Actions`}</VisuallyHidden>,
            meta: {sticky: 'right'},
            cell: ({row: {original: job}}) => (
                <Menu shadow="md" width={200} position="bottom-end">
                    <Menu.Target>
                        <ActionIcon variant="subtle" color="gray" aria-label={t`Actions`}>
                            <IconDotsVertical size={18}/>
                        </ActionIcon>
                    </Menu.Target>
                    <Menu.Dropdown>
                        <Menu.Item leftSection={<IconEye size={14}/>} onClick={() => handleViewDetails(job)}>
                            {t`View Details`}
                        </Menu.Item>
                        <Menu.Item leftSection={<IconRefresh size={14}/>} onClick={() => handleRetryJob(job.id)}>
                            {t`Retry`}
                        </Menu.Item>
                        <Menu.Item color="red" leftSection={<IconTrash size={14}/>} onClick={() => handleDeleteJob(job.id)}>
                            {t`Delete`}
                        </Menu.Item>
                    </Menu.Dropdown>
                </Menu>
            ),
        },
    ], []);

    return (
        <Container size="xl" p="xl">
            <Stack gap="lg">
                <Group justify="space-between">
                    <div>
                        <Title order={1}>{t`Failed Jobs`}</Title>
                        <Text c="dimmed" size="sm">{t`Monitor and manage failed background jobs`}</Text>
                    </div>
                    {totalJobs > 0 && (
                        <Group>
                            <Button
                                variant="outline"
                                color="blue"
                                leftSection={<IconRefresh size={16} />}
                                onClick={handleRetryAll}
                                loading={retryAllJobsMutation.isPending}
                            >
                                {t`Retry All`}
                            </Button>
                            <Button
                                variant="outline"
                                color="red"
                                leftSection={<IconTrash size={16} />}
                                onClick={handleDeleteAll}
                                loading={deleteAllJobsMutation.isPending}
                            >
                                {t`Delete All`}
                            </Button>
                        </Group>
                    )}
                </Group>

                <TableCard>
                    <ToolBar
                        embedded
                        searchComponent={() => (
                            <TextInput
                                placeholder={t`Search by job name or exception...`}
                                leftSection={<IconSearch size={16}/>}
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                            />
                        )}
                    />

                    {isLoading && <TableCardSkeleton/>}

                    {!isLoading && totalJobs === 0 && (
                        <div className={tableStyles.emptyState}>
                            <Text c="dimmed" size="lg">{t`No failed jobs`}</Text>
                        </div>
                    )}

                    {!isLoading && totalJobs > 0 && (
                        <TanStackTable data={jobsData?.data ?? []} columns={columns} noCard/>
                    )}

                    <TableCardFooter pagination={jobsData?.meta} page={page} onPageChange={setPage}/>
                </TableCard>
            </Stack>

            <Modal
                opened={detailModalOpened}
                onClose={closeDetailModal}
                title={t`Job Details`}
                size="xl"
            >
                {selectedJob && (
                    <Stack gap="md">
                        <div>
                            <Text size="sm" fw={500} c="dimmed">{t`Job Name`}</Text>
                            <Text>{selectedJob.job_name}</Text>
                            <Text size="xs" c="dimmed">{selectedJob.job_name_full}</Text>
                        </div>
                        <div>
                            <Text size="sm" fw={500} c="dimmed">{t`UUID`}</Text>
                            <Text size="sm">{selectedJob.uuid}</Text>
                        </div>
                        <div>
                            <Text size="sm" fw={500} c="dimmed">{t`Queue`}</Text>
                            <Badge variant="light">{selectedJob.queue}</Badge>
                        </div>
                        <div>
                            <Text size="sm" fw={500} c="dimmed">{t`Failed At`}</Text>
                            <Text size="sm">{selectedJob.failed_at}</Text>
                        </div>
                        <div>
                            <Text size="sm" fw={500} c="dimmed">{t`Exception`}</Text>
                            <ScrollArea h={300}>
                                <Code block style={{whiteSpace: 'pre-wrap', fontSize: '12px'}}>
                                    {selectedJob.exception}
                                </Code>
                            </ScrollArea>
                        </div>
                        <div>
                            <Text size="sm" fw={500} c="dimmed">{t`Payload`}</Text>
                            <ScrollArea h={200}>
                                <Code block style={{whiteSpace: 'pre-wrap', fontSize: '12px'}}>
                                    {formatPayloadForDisplay(selectedJob.payload)}
                                </Code>
                            </ScrollArea>
                        </div>
                        <Group justify="flex-end">
                            <Button
                                variant="outline"
                                color="blue"
                                leftSection={<IconRefresh size={16} />}
                                onClick={() => {
                                    handleRetryJob(selectedJob.id);
                                    closeDetailModal();
                                }}
                            >
                                {t`Retry Job`}
                            </Button>
                            <Button
                                variant="outline"
                                color="red"
                                leftSection={<IconTrash size={16} />}
                                onClick={() => {
                                    handleDeleteJob(selectedJob.id);
                                    closeDetailModal();
                                }}
                            >
                                {t`Delete Job`}
                            </Button>
                        </Group>
                    </Stack>
                )}
            </Modal>
        </Container>
    );
};

export default FailedJobs;
