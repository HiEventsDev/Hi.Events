import {t} from "@lingui/macro";
import {useDisclosure} from "@mantine/hooks";
import accountClasses from "../../ManageAccount.module.scss";
import {Card} from "../../../../../common/Card";
import {HeadingCard} from "../../../../../common/HeadingCard";
import {useGetContactAttributeDefinitions} from "../../../../../../queries/useGetContactAttributeDefinitions.ts";
import {ContactAttributeDefinition} from "../../../../../../types.ts";
import {Table, Menu, Button, Badge} from "@mantine/core";
import {IconDotsVertical, IconPencil, IconTrash} from "@tabler/icons-react";
import {useDeleteContactAttributeDefinition} from "../../../../../../mutations/useDeleteContactAttributeDefinition.ts";
import {showSuccess, showError} from "../../../../../../utilites/notifications.tsx";
import {CreateContactAttributeDefinitionModal} from "../../../../../modals/CreateContactAttributeDefinitionModal";
import {EditContactAttributeDefinitionModal} from "../../../../../modals/EditContactAttributeDefinitionModal";
import {useState} from "react";

export const ContactAttributes = () => {
    const [createModalOpen, {open: openCreateModal, close: closeCreateModal}] = useDisclosure(false);
    const [editModalOpen, {open: openEditModal, close: closeEditModal}] = useDisclosure(false);
    const [selectedDefinition, setSelectedDefinition] = useState<ContactAttributeDefinition>();
    const {data: definitions, isFetched} = useGetContactAttributeDefinitions();
    const deleteMutation = useDeleteContactAttributeDefinition();

    const handleEdit = (definition: ContactAttributeDefinition) => {
        setSelectedDefinition(definition);
        openEditModal();
    };

    const handleDelete = (definition: ContactAttributeDefinition) => {
        deleteMutation.mutate({definitionId: definition.id}, {
            onSuccess: () => showSuccess(t`Attribute definition deleted successfully`),
            onError: () => showError(t`Something went wrong while deleting the attribute definition`),
        });
    };

    return (
        <>
            <HeadingCard
                heading={t`Contact Attributes`}
                subHeading={t`Define custom attributes that can be stored on contacts`}
                buttonText={t`Add Attribute`}
                buttonAction={openCreateModal}
            />
            <Card className={accountClasses.tabContent}>
                {isFetched && definitions && definitions.data.length > 0 && (
                    <Table striped highlightOnHover>
                        <Table.Thead>
                            <Table.Tr>
                                <Table.Th>{t`Label`}</Table.Th>
                                <Table.Th>{t`Name`}</Table.Th>
                                <Table.Th>{t`Type`}</Table.Th>
                                <Table.Th>{t`Status`}</Table.Th>
                                <Table.Th/>
                            </Table.Tr>
                        </Table.Thead>
                        <Table.Tbody>
                            {definitions.data.map((definition) => (
                                <Table.Tr key={definition.id}>
                                    <Table.Td>{definition.label}</Table.Td>
                                    <Table.Td><code>{definition.name}</code></Table.Td>
                                    <Table.Td>{definition.type}</Table.Td>
                                    <Table.Td>
                                        <Badge color={definition.is_active ? 'green' : 'gray'} variant="light">
                                            {definition.is_active ? t`Active` : t`Inactive`}
                                        </Badge>
                                    </Table.Td>
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
                                                    onClick={() => handleEdit(definition)}
                                                >
                                                    {t`Edit`}
                                                </Menu.Item>
                                                <Menu.Item
                                                    color="red"
                                                    leftSection={<IconTrash size={14}/>}
                                                    onClick={() => handleDelete(definition)}
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

                {isFetched && definitions && definitions.data.length === 0 && (
                    <p>{t`No contact attribute definitions have been added.`}</p>
                )}
            </Card>

            {createModalOpen && <CreateContactAttributeDefinitionModal onClose={closeCreateModal}/>}
            {editModalOpen && selectedDefinition && (
                <EditContactAttributeDefinitionModal definition={selectedDefinition} onClose={closeEditModal}/>
            )}
        </>
    );
};

export default ContactAttributes;
