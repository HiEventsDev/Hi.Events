import {t} from "@lingui/macro";
import {useMemo, useState} from "react";
import {ActionIcon, Alert, Button, Group, Menu, Select, Table, Text, TextInput, UnstyledButton} from "@mantine/core";
import {IconDotsVertical, IconPencil, IconSearch, IconSortAscending, IconSortDescending, IconTrash} from "@tabler/icons-react";
import {useDisclosure} from "@mantine/hooks";
import {Card} from "../../../common/Card";
import {Pagination} from "../../../common/Pagination";
import {TableSkeleton} from "../../../common/TableSkeleton";
import {Contact, QueryFilterOperator, QueryFilters} from "../../../../types.ts";
import {useGetContacts} from "../../../../queries/useGetContacts.ts";
import {useGetEvents} from "../../../../queries/useGetEvents.ts";
import {useDeleteContact} from "../../../../mutations/useDeleteContact.ts";
import {showError, showSuccess} from "../../../../utilites/notifications.tsx";
import {CreateContactModal} from "../../../modals/CreateContactModal";
import {EditContactModal} from "../../../modals/EditContactModal";

interface SortableThProps {
    label: string;
    field: string;
    sortBy: string;
    sortDir: string;
    onSort: (field: string) => void;
}

const SortableTh = ({label, field, sortBy, sortDir, onSort}: SortableThProps) => {
    const isActive = sortBy === field;
    const Icon = isActive && sortDir === 'asc' ? IconSortAscending : IconSortDescending;
    return (
        <Table.Th>
            <UnstyledButton onClick={() => onSort(field)} style={{display: 'flex', alignItems: 'center', gap: 4, fontWeight: 700}}>
                {label}
                {isActive && <Icon size={14} style={{opacity: 0.6}}/>}
            </UnstyledButton>
        </Table.Th>
    );
};

export const ContactsTab = () => {
    const [page, setPage] = useState(1);
    const [query, setQuery] = useState('');
    const [eventFilter, setEventFilter] = useState<string | null>(null);
    const [sortBy, setSortBy] = useState('created_at');
    const [sortDir, setSortDir] = useState('desc');
    const [createModalOpen, {open: openCreateModal, close: closeCreateModal}] = useDisclosure(false);
    const [editModalOpen, {open: openEditModal, close: closeEditModal}] = useDisclosure(false);
    const [selectedContact, setSelectedContact] = useState<Contact>();

    const eventsQuery = useGetEvents({pageNumber: 1, perPage: 100});
    const eventOptions = useMemo(() => {
        const events = eventsQuery.data?.data ?? [];
        return events.map((e: any) => ({value: String(e.id), label: e.title}));
    }, [eventsQuery.data]);

    const handleSort = (field: string) => {
        if (sortBy === field) {
            setSortDir(sortDir === 'asc' ? 'desc' : 'asc');
        } else {
            setSortBy(field);
            setSortDir('asc');
        }
        setPage(1);
    };

    const filterFields: Record<string, any> = {};
    if (eventFilter) {
        filterFields.event_id = {operator: QueryFilterOperator.Equals, value: eventFilter};
    }

    const searchParams: QueryFilters = {
        pageNumber: page,
        perPage: 20,
        query: query || undefined,
        filterFields: Object.keys(filterFields).length > 0 ? filterFields : undefined,
        sortBy,
        sortDirection: sortDir,
    };

    const contactsQuery = useGetContacts(searchParams);
    const contacts = contactsQuery.data?.data;
    const pagination = contactsQuery.data?.meta;
    const deleteMutation = useDeleteContact();

    const handleEdit = (contact: Contact) => {
        setSelectedContact(contact);
        openEditModal();
    };

    const handleDelete = (contact: Contact) => {
        deleteMutation.mutate({contactId: contact.id}, {
            onSuccess: () => showSuccess(t`Contact deleted successfully`),
            onError: () => showError(t`Something went wrong while deleting the contact`),
        });
    };

    return (
        <>
            <Card>
                <Group gap="sm" wrap="wrap" mb="md" align="center">
                    <TextInput
                        placeholder={t`Search by name or email...`}
                        leftSection={<IconSearch size={16}/>}
                        value={query}
                        onChange={(e) => { setQuery(e.currentTarget.value); setPage(1); }}
                        size="sm"
                        style={{flex: 1, minWidth: 220, marginBottom: 0}}
                    />
                    <Select
                        placeholder={t`Filter by event`}
                        data={eventOptions}
                        value={eventFilter}
                        onChange={(val) => { setEventFilter(val); setPage(1); }}
                        clearable
                        searchable
                        size="sm"
                        style={{width: 240, marginBottom: 0}}
                    />
                    <Button onClick={openCreateModal} size="sm">
                        {t`Add Contact`}
                    </Button>
                </Group>

                {contactsQuery.isLoading && <TableSkeleton isVisible/>}

                {!!contactsQuery.error && (
                    <Alert color="red" radius="md">
                        {t`Failed to load contacts`}
                    </Alert>
                )}

                {!contactsQuery.isLoading && !contactsQuery.error && contacts && contacts.length === 0 && (
                    <Text c="dimmed" ta="center" py="xl">{t`No contacts found.`}</Text>
                )}

                {contacts && contacts.length > 0 && (
                    <>
                        <Table striped highlightOnHover>
                            <Table.Thead>
                                <Table.Tr>
                                    <SortableTh label={t`Email`} field="email" sortBy={sortBy} sortDir={sortDir} onSort={handleSort}/>
                                    <SortableTh label={t`First Name`} field="first_name" sortBy={sortBy} sortDir={sortDir} onSort={handleSort}/>
                                    <SortableTh label={t`Last Name`} field="last_name" sortBy={sortBy} sortDir={sortDir} onSort={handleSort}/>
                                    <SortableTh label={t`Created`} field="created_at" sortBy={sortBy} sortDir={sortDir} onSort={handleSort}/>
                                    <Table.Th/>
                                </Table.Tr>
                            </Table.Thead>
                            <Table.Tbody>
                                {contacts.map((contact) => (
                                    <Table.Tr key={contact.id}>
                                        <Table.Td>{contact.email}</Table.Td>
                                        <Table.Td>{contact.first_name || '-'}</Table.Td>
                                        <Table.Td>{contact.last_name || '-'}</Table.Td>
                                        <Table.Td>{contact.created_at ? new Date(contact.created_at).toLocaleDateString() : '-'}</Table.Td>
                                        <Table.Td>
                                            <Menu shadow="md" width={200}>
                                                <Menu.Target>
                                                    <ActionIcon variant="subtle">
                                                        <IconDotsVertical size={14}/>
                                                    </ActionIcon>
                                                </Menu.Target>
                                                <Menu.Dropdown>
                                                    <Menu.Item leftSection={<IconPencil size={14}/>} onClick={() => handleEdit(contact)}>
                                                        {t`Edit`}
                                                    </Menu.Item>
                                                    <Menu.Item color="red" leftSection={<IconTrash size={14}/>} onClick={() => handleDelete(contact)}>
                                                        {t`Delete`}
                                                    </Menu.Item>
                                                </Menu.Dropdown>
                                            </Menu>
                                        </Table.Td>
                                    </Table.Tr>
                                ))}
                            </Table.Tbody>
                        </Table>

                        {pagination && Number(pagination.last_page) > 1 && (
                            <Pagination
                                value={page}
                                onChange={setPage}
                                total={Number(pagination.last_page)}
                            />
                        )}
                    </>
                )}
            </Card>

            {createModalOpen && <CreateContactModal onClose={closeCreateModal}/>}
            {editModalOpen && selectedContact && <EditContactModal contact={selectedContact} onClose={closeEditModal}/>}
        </>
    );
};
