import {Container, Title, TextInput, Skeleton, Pagination, Stack, Table, Text, Badge, Group, Select, Modal, ActionIcon, Tooltip, Button} from "@mantine/core";
import {t} from "@lingui/macro";
import {IconSearch, IconMailOff, IconTrash, IconPlus} from "@tabler/icons-react";
import {useState, useEffect} from "react";
import {useGetEmailSuppressions} from "../../../../queries/useGetEmailSuppressions";
import {useDeleteEmailSuppression} from "../../../../mutations/useDeleteEmailSuppression";
import {useCreateEmailSuppression} from "../../../../mutations/useCreateEmailSuppression";
import {relativeDate} from "../../../../utilites/dates";
import {useDisclosure} from "@mantine/hooks";
import {showSuccess, showError} from "../../../../utilites/notifications";
import {confirmationDialog} from "../../../../utilites/confirmationDialog";
import {IdParam} from "../../../../types";
import {useForm} from "@mantine/form";
import tableStyles from "../../../../styles/admin-table.module.scss";

const EmailSuppressions = () => {
    const [page, setPage] = useState(1);
    const [search, setSearch] = useState("");
    const [debouncedSearch, setDebouncedSearch] = useState("");
    const [reasonFilter, setReasonFilter] = useState<string | null>(null);
    const [sourceFilter, setSourceFilter] = useState<string | null>(null);
    const [createModalOpened, {open: openCreateModal, close: closeCreateModal}] = useDisclosure(false);

    const {data: suppressionsData, isLoading} = useGetEmailSuppressions({
        page,
        per_page: 20,
        search: debouncedSearch,
        reason: reasonFilter || undefined,
        source: sourceFilter || undefined,
    });

    const deleteMutation = useDeleteEmailSuppression();
    const createMutation = useCreateEmailSuppression();

    const form = useForm({
        initialValues: {
            email: '',
            reason: 'bounce',
            bounce_type: 'Permanent',
        },
        validate: {
            email: (value) => (!value || !value.includes('@') ? t`Please enter a valid email address` : null),
            reason: (value) => (!value ? t`Reason is required` : null),
        },
    });

    const handleDelete = (id: IdParam) => {
        confirmationDialog(
            t`Are you sure you want to remove this suppression? The email address will be able to receive messages again.`,
            () => {
                deleteMutation.mutate({id}, {
                    onSuccess: () => showSuccess(t`Suppression removed successfully`),
                    onError: () => showError(t`Failed to remove suppression`),
                });
            },
        );
    };

    const handleCreate = (values: typeof form.values) => {
        createMutation.mutate({
            email: values.email,
            reason: values.reason,
            bounce_type: values.reason === 'bounce' ? values.bounce_type : undefined,
        }, {
            onSuccess: () => {
                showSuccess(t`Email suppression added successfully`);
                form.reset();
                closeCreateModal();
            },
            onError: () => showError(t`Failed to add suppression`),
        });
    };

    useEffect(() => {
        const timer = setTimeout(() => {
            setDebouncedSearch(search);
            setPage(1);
        }, 500);

        return () => clearTimeout(timer);
    }, [search]);

    const getReasonColor = (reason: string) => {
        switch (reason.toLowerCase()) {
            case 'bounce':
                return 'red';
            case 'complaint':
                return 'orange';
            default:
                return 'gray';
        }
    };

    const getSourceColor = (source: string) => {
        switch (source.toLowerCase()) {
            case 'ses_notification':
                return 'blue';
            case 'manual':
                return 'gray';
            default:
                return 'gray';
        }
    };

    const getSourceLabel = (source: string) => {
        switch (source.toLowerCase()) {
            case 'ses_notification':
                return t`SES`;
            case 'manual':
                return t`Manual`;
            default:
                return source;
        }
    };

    const totalSuppressions = suppressionsData?.meta?.total || 0;

    return (
        <Container size="xl" p="xl">
            <Stack gap="lg">
                <Group justify="space-between">
                    <div>
                        <Title order={1}>{t`Email Suppressions`}</Title>
                        <Text c="dimmed" size="sm">{t`Manage bounced and suppressed email addresses`}</Text>
                    </div>
                    <Button leftSection={<IconPlus size={16} />} onClick={openCreateModal}>
                        {t`Add Suppression`}
                    </Button>
                </Group>

                <Group>
                    <TextInput
                        placeholder={t`Search by email address...`}
                        leftSection={<IconSearch size={16} />}
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        style={{flex: 1}}
                    />
                    <Select
                        placeholder={t`Reason`}
                        clearable
                        data={[
                            {value: 'bounce', label: t`Bounce`},
                            {value: 'complaint', label: t`Complaint`},
                        ]}
                        value={reasonFilter}
                        onChange={setReasonFilter}
                        w={150}
                    />
                    <Select
                        placeholder={t`Source`}
                        clearable
                        data={[
                            {value: 'ses_notification', label: t`SES`},
                            {value: 'manual', label: t`Manual`},
                        ]}
                        value={sourceFilter}
                        onChange={setSourceFilter}
                        w={150}
                    />
                </Group>

                {isLoading ? (
                    <Stack gap="md">
                        <Skeleton height={50} radius="md" />
                        <Skeleton height={400} radius="md" />
                    </Stack>
                ) : totalSuppressions === 0 ? (
                    <div className={tableStyles.emptyState}>
                        <IconMailOff size={48} color="gray" style={{marginBottom: 8}} />
                        <Text c="dimmed" size="lg">{t`No suppressed emails found`}</Text>
                    </div>
                ) : (
                    <div className={tableStyles.tableWrapper}>
                        <div className={tableStyles.tableScroll}>
                            <Table className={tableStyles.table} highlightOnHover>
                                <Table.Thead>
                                    <Table.Tr>
                                        <Table.Th>{t`Email`}</Table.Th>
                                        <Table.Th>{t`Reason`}</Table.Th>
                                        <Table.Th>{t`Type`}</Table.Th>
                                        <Table.Th>{t`Source`}</Table.Th>
                                        <Table.Th>{t`Account`}</Table.Th>
                                        <Table.Th>{t`Date`}</Table.Th>
                                        <Table.Th style={{width: 50}}></Table.Th>
                                    </Table.Tr>
                                </Table.Thead>
                                <Table.Tbody>
                                    {suppressionsData?.data?.map((suppression) => (
                                        <Table.Tr key={suppression.id}>
                                            <Table.Td>
                                                <Group gap="xs">
                                                    <IconMailOff size={16} color="gray" />
                                                    <Text size="sm" fw={500}>
                                                        {suppression.email}
                                                    </Text>
                                                </Group>
                                            </Table.Td>
                                            <Table.Td>
                                                <Badge color={getReasonColor(suppression.reason)} variant="light">
                                                    {suppression.reason}
                                                </Badge>
                                            </Table.Td>
                                            <Table.Td>
                                                <Text size="sm" c="dimmed">
                                                    {suppression.bounce_type || suppression.complaint_type || '-'}
                                                </Text>
                                            </Table.Td>
                                            <Table.Td>
                                                <Badge color={getSourceColor(suppression.source)} variant="light">
                                                    {getSourceLabel(suppression.source)}
                                                </Badge>
                                            </Table.Td>
                                            <Table.Td>
                                                <Text size="sm" c="dimmed" lineClamp={1} maw={120}>
                                                    {suppression.account_name || '-'}
                                                </Text>
                                            </Table.Td>
                                            <Table.Td>
                                                <Text size="sm">{relativeDate(suppression.created_at)}</Text>
                                            </Table.Td>
                                            <Table.Td>
                                                <Tooltip label={t`Remove Suppression`}>
                                                    <ActionIcon
                                                        variant="subtle"
                                                        color="red"
                                                        onClick={() => handleDelete(suppression.id)}
                                                        loading={deleteMutation.isPending}
                                                    >
                                                        <IconTrash size={16} />
                                                    </ActionIcon>
                                                </Tooltip>
                                            </Table.Td>
                                        </Table.Tr>
                                    ))}
                                </Table.Tbody>
                            </Table>
                        </div>
                    </div>
                )}

                {suppressionsData?.meta && suppressionsData.meta.last_page > 1 && (
                    <Pagination
                        total={suppressionsData.meta.last_page}
                        value={page}
                        onChange={setPage}
                        mt="md"
                    />
                )}
            </Stack>

            <Modal
                opened={createModalOpened}
                onClose={closeCreateModal}
                title={t`Add Email Suppression`}
            >
                <form onSubmit={form.onSubmit(handleCreate)}>
                    <Stack gap="md">
                        <TextInput
                            label={t`Email Address`}
                            placeholder="user@example.com"
                            required
                            {...form.getInputProps('email')}
                        />
                        <Select
                            label={t`Reason`}
                            required
                            data={[
                                {value: 'bounce', label: t`Bounce`},
                                {value: 'complaint', label: t`Complaint`},
                            ]}
                            {...form.getInputProps('reason')}
                        />
                        {form.values.reason === 'bounce' && (
                            <Select
                                label={t`Bounce Type`}
                                data={[
                                    {value: 'Permanent', label: t`Permanent`},
                                    {value: 'Transient', label: t`Transient`},
                                    {value: 'Undetermined', label: t`Undetermined`},
                                ]}
                                {...form.getInputProps('bounce_type')}
                            />
                        )}
                        <Button type="submit" loading={createMutation.isPending}>
                            {t`Add Suppression`}
                        </Button>
                    </Stack>
                </form>
            </Modal>
        </Container>
    );
};

export default EmailSuppressions;
