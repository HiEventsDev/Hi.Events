import {Container, Title, TextInput, Skeleton, Pagination, Stack, Table, Button, Group, Text, Badge, Modal, Code, ScrollArea, ActionIcon, Tooltip} from "@mantine/core";
import {t} from "@lingui/macro";
import {IconSearch, IconTrash, IconRefresh, IconEye} from "@tabler/icons-react";
import {useState, useEffect} from "react";
import {useGetAdminFailedJobs} from "../../../../queries/useGetAdminFailedJobs";
import {useDeleteFailedJob} from "../../../../mutations/useDeleteFailedJob";
import {useDeleteAllFailedJobs} from "../../../../mutations/useDeleteAllFailedJobs";
import {useRetryFailedJob} from "../../../../mutations/useRetryFailedJob";
import {useRetryAllFailedJobs} from "../../../../mutations/useRetryAllFailedJobs";
import {showError, showSuccess} from "../../../../utilites/notifications";
import {AdminFailedJob} from "../../../../api/admin.client";
import {useDisclosure} from "@mantine/hooks";
import {relativeDate} from "../../../../utilites/dates";

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

    const handleRetryJob = (jobId: number | string) => {
        retryJobMutation.mutate(jobId, {
            onSuccess: () => {
                showSuccess(t`Job queued for retry`);
            },
            onError: () => {
                showError(t`Failed to retry job`);
            }
        });
    };

    const handleDeleteJob = (jobId: number | string) => {
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

                <TextInput
                    placeholder={t`Search by job name or exception...`}
                    leftSection={<IconSearch size={16} />}
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                />

                {isLoading ? (
                    <Stack gap="md">
                        <Skeleton height={50} radius="md" />
                        <Skeleton height={400} radius="md" />
                    </Stack>
                ) : totalJobs === 0 ? (
                    <Text c="dimmed" ta="center" py="xl">{t`No failed jobs`}</Text>
                ) : (
                    <Table striped highlightOnHover>
                        <Table.Thead>
                            <Table.Tr>
                                <Table.Th>{t`Job`}</Table.Th>
                                <Table.Th>{t`Queue`}</Table.Th>
                                <Table.Th>{t`Failed At`}</Table.Th>
                                <Table.Th>{t`Exception`}</Table.Th>
                                <Table.Th w={120}>{t`Actions`}</Table.Th>
                            </Table.Tr>
                        </Table.Thead>
                        <Table.Tbody>
                            {jobsData?.data?.map((job) => (
                                <Table.Tr key={job.id}>
                                    <Table.Td>
                                        <Text size="sm" fw={500}>{job.job_name}</Text>
                                        <Text size="xs" c="dimmed">{job.uuid}</Text>
                                    </Table.Td>
                                    <Table.Td>
                                        <Badge variant="light">{job.queue}</Badge>
                                    </Table.Td>
                                    <Table.Td>
                                        <Text size="sm">{relativeDate(job.failed_at)}</Text>
                                    </Table.Td>
                                    <Table.Td>
                                        <Text size="sm" lineClamp={1} maw={300}>
                                            {job.exception_summary}
                                        </Text>
                                    </Table.Td>
                                    <Table.Td>
                                        <Group gap="xs">
                                            <Tooltip label={t`View Details`}>
                                                <ActionIcon
                                                    variant="subtle"
                                                    color="gray"
                                                    onClick={() => handleViewDetails(job)}
                                                >
                                                    <IconEye size={16} />
                                                </ActionIcon>
                                            </Tooltip>
                                            <Tooltip label={t`Retry`}>
                                                <ActionIcon
                                                    variant="subtle"
                                                    color="blue"
                                                    onClick={() => handleRetryJob(job.id)}
                                                    loading={retryJobMutation.isPending}
                                                >
                                                    <IconRefresh size={16} />
                                                </ActionIcon>
                                            </Tooltip>
                                            <Tooltip label={t`Delete`}>
                                                <ActionIcon
                                                    variant="subtle"
                                                    color="red"
                                                    onClick={() => handleDeleteJob(job.id)}
                                                    loading={deleteJobMutation.isPending}
                                                >
                                                    <IconTrash size={16} />
                                                </ActionIcon>
                                            </Tooltip>
                                        </Group>
                                    </Table.Td>
                                </Table.Tr>
                            ))}
                        </Table.Tbody>
                    </Table>
                )}

                {jobsData?.meta && jobsData.meta.last_page > 1 && (
                    <Pagination
                        total={jobsData.meta.last_page}
                        value={page}
                        onChange={setPage}
                        mt="md"
                    />
                )}
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
