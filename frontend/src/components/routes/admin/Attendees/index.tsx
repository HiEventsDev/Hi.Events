import {Container, Title, TextInput, Skeleton, Pagination, Stack, Badge, Table, Text, Button, Group, Modal, Textarea} from "@mantine/core";
import {t} from "@lingui/macro";
import {IconSearch, IconChevronDown, IconChevronUp, IconEdit} from "@tabler/icons-react";
import {useState, useEffect} from "react";
import {useGetAllAdminAttendees} from "../../../../queries/useGetAllAdminAttendees";
import {useEditAdminAttendee} from "../../../../mutations/useEditAdminAttendee";
import {AdminAttendee} from "../../../../api/admin.client";
import {prettyDate} from "../../../../utilites/dates";
import {showSuccess, showError} from "../../../../utilites/notifications";
import tableStyles from "../../../../styles/admin-table.module.scss";

const getStatusBadgeColor = (status: string) => {
    switch (status.toUpperCase()) {
        case 'ACTIVE':
            return 'green';
        case 'CANCELLED':
            return 'red';
        case 'AWAITING_PAYMENT':
            return 'yellow';
        default:
            return 'gray';
    }
};

const Attendees = () => {
    const [page, setPage] = useState(1);
    const [search, setSearch] = useState("");
    const [debouncedSearch, setDebouncedSearch] = useState("");
    const [sortBy, setSortBy] = useState("created_at");
    const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>("desc");
    const [editingAttendee, setEditingAttendee] = useState<AdminAttendee | null>(null);
    const [editForm, setEditForm] = useState({first_name: '', last_name: '', email: '', notes: ''});

    const {data: attendeesData, isLoading} = useGetAllAdminAttendees({
        page,
        per_page: 20,
        search: debouncedSearch,
        sort_by: sortBy,
        sort_direction: sortDirection,
    });

    const editMutation = useEditAdminAttendee();

    useEffect(() => {
        const timer = setTimeout(() => {
            setDebouncedSearch(search);
            setPage(1);
        }, 500);
        return () => clearTimeout(timer);
    }, [search]);

    const handleSort = (column: string) => {
        if (sortBy === column) {
            setSortDirection(sortDirection === 'asc' ? 'desc' : 'asc');
        } else {
            setSortBy(column);
            setSortDirection('desc');
        }
    };

    const openEditModal = (attendee: AdminAttendee) => {
        setEditingAttendee(attendee);
        setEditForm({
            first_name: attendee.first_name,
            last_name: attendee.last_name,
            email: attendee.email,
            notes: attendee.notes || '',
        });
    };

    const handleSaveEdit = () => {
        if (!editingAttendee) return;
        editMutation.mutate(
            {attendeeId: editingAttendee.id, data: editForm},
            {
                onSuccess: () => {
                    showSuccess(t`Attendee updated successfully`);
                    setEditingAttendee(null);
                },
                onError: () => {
                    showError(t`Failed to update attendee`);
                },
            }
        );
    };

    const SortIcon = ({column}: {column: string}) => {
        if (sortBy !== column) return null;
        return sortDirection === 'asc' ? <IconChevronUp size={14} /> : <IconChevronDown size={14} />;
    };

    const attendees = attendeesData?.data || [];

    return (
        <Container size="xl" p="xl">
            <Stack gap="lg">
                <Title order={1}>{t`Attendees`}</Title>

                <TextInput
                    placeholder={t`Search by name, email, or ticket ID...`}
                    leftSection={<IconSearch size={16} />}
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                />

                {isLoading ? (
                    <Stack gap="md">
                        <Skeleton height={50} radius="md" />
                        <Skeleton height={400} radius="md" />
                    </Stack>
                ) : attendees.length === 0 ? (
                    <div className={tableStyles.emptyState}>
                        <Text size="lg" c="dimmed">{t`No attendees found`}</Text>
                    </div>
                ) : (
                    <div className={tableStyles.tableWrapper}>
                        <div className={tableStyles.tableScroll}>
                            <Table className={tableStyles.table} highlightOnHover>
                                <Table.Thead>
                                    <Table.Tr>
                                        <Table.Th>
                                            <Button variant="subtle" size="compact-sm" onClick={() => handleSort('first_name')} rightSection={<SortIcon column="first_name" />} className={tableStyles.sortButton}>
                                                {t`Name`}
                                            </Button>
                                        </Table.Th>
                                        <Table.Th>
                                            <Button variant="subtle" size="compact-sm" onClick={() => handleSort('email')} rightSection={<SortIcon column="email" />} className={tableStyles.sortButton}>
                                                {t`Email`}
                                            </Button>
                                        </Table.Th>
                                        <Table.Th>{t`Product`}</Table.Th>
                                        <Table.Th>{t`Event`}</Table.Th>
                                        <Table.Th>{t`Account`}</Table.Th>
                                        <Table.Th>{t`Status`}</Table.Th>
                                        <Table.Th>
                                            <Button variant="subtle" size="compact-sm" onClick={() => handleSort('created_at')} rightSection={<SortIcon column="created_at" />} className={tableStyles.sortButton}>
                                                {t`Date`}
                                            </Button>
                                        </Table.Th>
                                        <Table.Th>{t`Actions`}</Table.Th>
                                    </Table.Tr>
                                </Table.Thead>
                                <Table.Tbody>
                                    {attendees.map((attendee) => (
                                        <Table.Tr key={attendee.id}>
                                            <Table.Td>
                                                <Text size="sm" fw={500}>{attendee.first_name} {attendee.last_name}</Text>
                                            </Table.Td>
                                            <Table.Td>
                                                <Text size="sm">{attendee.email}</Text>
                                            </Table.Td>
                                            <Table.Td>
                                                <Text size="sm">{attendee.product_title || '-'}</Text>
                                            </Table.Td>
                                            <Table.Td>
                                                <Text size="sm">{attendee.event_title || '-'}</Text>
                                            </Table.Td>
                                            <Table.Td>
                                                <Text size="sm">{attendee.account_name || '-'}</Text>
                                            </Table.Td>
                                            <Table.Td>
                                                <Badge color={getStatusBadgeColor(attendee.status)} size="sm" variant="light">
                                                    {attendee.status}
                                                </Badge>
                                            </Table.Td>
                                            <Table.Td>
                                                <Text size="sm">{prettyDate(attendee.created_at, 'UTC')}</Text>
                                            </Table.Td>
                                            <Table.Td>
                                                <Button
                                                    variant="subtle"
                                                    size="compact-sm"
                                                    leftSection={<IconEdit size={14} />}
                                                    onClick={() => openEditModal(attendee)}
                                                >
                                                    {t`Edit`}
                                                </Button>
                                            </Table.Td>
                                        </Table.Tr>
                                    ))}
                                </Table.Tbody>
                            </Table>
                        </div>
                    </div>
                )}

                {attendeesData?.meta && attendeesData.meta.last_page > 1 && (
                    <Pagination
                        total={attendeesData.meta.last_page}
                        value={page}
                        onChange={setPage}
                        mt="md"
                    />
                )}
            </Stack>

            <Modal
                opened={!!editingAttendee}
                onClose={() => setEditingAttendee(null)}
                title={t`Edit Attendee`}
                size="md"
            >
                <Stack gap="md">
                    <TextInput
                        label={t`First Name`}
                        value={editForm.first_name}
                        onChange={(e) => setEditForm({...editForm, first_name: e.target.value})}
                    />
                    <TextInput
                        label={t`Last Name`}
                        value={editForm.last_name}
                        onChange={(e) => setEditForm({...editForm, last_name: e.target.value})}
                    />
                    <TextInput
                        label={t`Email`}
                        value={editForm.email}
                        onChange={(e) => setEditForm({...editForm, email: e.target.value})}
                    />
                    <Textarea
                        label={t`Notes`}
                        value={editForm.notes}
                        onChange={(e) => setEditForm({...editForm, notes: e.target.value})}
                        minRows={3}
                    />
                    <Group justify="flex-end">
                        <Button variant="default" onClick={() => setEditingAttendee(null)}>
                            {t`Cancel`}
                        </Button>
                        <Button onClick={handleSaveEdit} loading={editMutation.isPending}>
                            {t`Save`}
                        </Button>
                    </Group>
                </Stack>
            </Modal>
        </Container>
    );
};

export default Attendees;
