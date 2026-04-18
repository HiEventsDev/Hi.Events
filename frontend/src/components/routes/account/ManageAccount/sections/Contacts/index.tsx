import {t} from "@lingui/macro";
import {useDisclosure} from "@mantine/hooks";
import accountClasses from "../../ManageAccount.module.scss";
import {Card} from "../../../../../common/Card";
import {HeadingCard} from "../../../../../common/HeadingCard";
import {useGetContacts} from "../../../../../../queries/useGetContacts.ts";
import {useFilterQueryParamSync} from "../../../../../../hooks/useFilterQueryParamSync.ts";
import {Contact, QueryFilters} from "../../../../../../types.ts";
import {Table, Menu, Button, TextInput} from "@mantine/core";
import {IconDotsVertical, IconPencil, IconTrash, IconSearch} from "@tabler/icons-react";
import {useDeleteContact} from "../../../../../../mutations/useDeleteContact.ts";
import {showSuccess, showError} from "../../../../../../utilites/notifications.tsx";
import {CreateContactModal} from "../../../../../modals/CreateContactModal";
import {EditContactModal} from "../../../../../modals/EditContactModal";
import {Pagination} from "../../../../../common/Pagination";
import {TableSkeleton} from "../../../../../common/TableSkeleton";
import {useState} from "react";

export const Contacts = () => {
    const [createModalOpen, {open: openCreateModal, close: closeCreateModal}] = useDisclosure(false);
    const [editModalOpen, {open: openEditModal, close: closeEditModal}] = useDisclosure(false);
    const [selectedContact, setSelectedContact] = useState<Contact>();
    const [searchParams, setSearchParams] = useFilterQueryParamSync();
    const contactsQuery = useGetContacts(searchParams as QueryFilters);
    const contacts = contactsQuery?.data?.data;
    const pagination = contactsQuery?.data?.meta;
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
            <HeadingCard
                heading={t`Contacts`}
                subHeading={t`Manage contacts linked to attendees across your events`}
                buttonText={t`Add Contact`}
                buttonAction={openCreateModal}
            />
            <Card className={accountClasses.tabContent}>
                <TextInput
                    placeholder={t`Search by name or email...`}
                    leftSection={<IconSearch size={16}/>}
                    mb="md"
                    value={searchParams.query || ''}
                    onChange={(e) => setSearchParams({query: e.currentTarget.value, pageNumber: 1})}
                />

                <TableSkeleton isVisible={!contacts}/>

                {contacts && contacts.length > 0 && (
                    <Table striped highlightOnHover>
                        <Table.Thead>
                            <Table.Tr>
                                <Table.Th>{t`Email`}</Table.Th>
                                <Table.Th>{t`First Name`}</Table.Th>
                                <Table.Th>{t`Last Name`}</Table.Th>
                                <Table.Th>{t`Created`}</Table.Th>
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
                                                <Button size="compact-xs" variant="transparent">
                                                    <IconDotsVertical size={14}/>
                                                </Button>
                                            </Menu.Target>
                                            <Menu.Dropdown>
                                                <Menu.Item
                                                    leftSection={<IconPencil size={14}/>}
                                                    onClick={() => handleEdit(contact)}
                                                >
                                                    {t`Edit`}
                                                </Menu.Item>
                                                <Menu.Item
                                                    color="red"
                                                    leftSection={<IconTrash size={14}/>}
                                                    onClick={() => handleDelete(contact)}
                                                >
                                                    {t`Delete`}
                                                </Menu.Item>
                                            </Menu.Dropdown>
                                        </Menu>
                                    </Table.Td>
                                </Table.Tr>
                            ))}
                        </Table.Tbody>
                    </Table>
                )}

                {contacts && contacts.length === 0 && (
                    <p>{t`No contacts found.`}</p>
                )}

                {!!contacts?.length && pagination && (
                    <Pagination
                        value={searchParams.pageNumber}
                        onChange={(value) => setSearchParams({pageNumber: value})}
                        total={Number(pagination.last_page)}
                    />
                )}
            </Card>

            {createModalOpen && <CreateContactModal onClose={closeCreateModal}/>}
            {editModalOpen && selectedContact && <EditContactModal contact={selectedContact} onClose={closeEditModal}/>}
        </>
    );
};

export default Contacts;
