import {t} from "@lingui/macro";
import {useMemo, useState} from "react";
import {ActionIcon, Alert, Badge, Button, Group, Menu, Select, Table, Text, TextInput, UnstyledButton} from "@mantine/core";
import {IconDotsVertical, IconLink, IconPencil, IconSearch, IconTrash} from "@tabler/icons-react";
import {useDisclosure} from "@mantine/hooks";
import {Card} from "../../../../common/Card";
import {SortableTh} from "../../../../common/SortableTh";
import {TableSkeleton} from "../../../../common/TableSkeleton";
import {ContactAttributeDefinition} from "../../../../../types.ts";
import {useGetContactAttributeDefinitions} from "../../../../../queries/useGetContactAttributeDefinitions.ts";
import {useDeleteContactAttributeDefinition} from "../../../../../mutations/useDeleteContactAttributeDefinition.ts";
import {showError, showSuccess} from "../../../../../utilites/notifications.tsx";
import {CreateContactAttributeDefinitionModal} from "../../../../modals/CreateContactAttributeDefinitionModal";
import {EditContactAttributeDefinitionModal} from "../../../../modals/EditContactAttributeDefinitionModal";
import {LinkedQuestionsDrawer} from "./LinkedQuestionsDrawer";
import {useGetEvents} from "../../../../../queries/useGetEvents.ts";
import {useQuery} from "@tanstack/react-query";
import {questionClient} from "../../../../../api/question.client.ts";
import {GET_EVENT_QUESTIONS_QUERY_KEY} from "../../../../../queries/useGetEventQuestions.ts";

type SortField = 'label' | 'name' | 'type' | 'is_active' | 'linked_event_count';

export const ExtendedAttributesTab = () => {
    const [createModalOpen, {open: openCreateModal, close: closeCreateModal}] = useDisclosure(false);
    const [editModalOpen, {open: openEditModal, close: closeEditModal}] = useDisclosure(false);
    const [drawerOpen, {open: openDrawer, close: closeDrawer}] = useDisclosure(false);
    const [selectedDefinition, setSelectedDefinition] = useState<ContactAttributeDefinition>();
    const [drawerDefinition, setDrawerDefinition] = useState<ContactAttributeDefinition>();
    const [query, setQuery] = useState('');
    const [eventFilter, setEventFilter] = useState<string | null>(null);
    const [sortBy, setSortBy] = useState<SortField>('label');
    const [sortDir, setSortDir] = useState<'asc' | 'desc'>('asc');

    const definitionsQuery = useGetContactAttributeDefinitions();
    const deleteMutation = useDeleteContactAttributeDefinition();
    const eventsQuery = useGetEvents({pageNumber: 1, perPage: 100});

    const eventOptions = useMemo(
        () => (eventsQuery.data?.data ?? []).map((e: any) => ({value: String(e.id), label: e.title})),
        [eventsQuery.data],
    );

    const eventFilterDefinitionIds = useEventFilteredDefinitionIds(eventFilter);

    const handleSort = (field: string) => {
        if (sortBy === field) {
            setSortDir(sortDir === 'asc' ? 'desc' : 'asc');
        } else {
            setSortBy(field as SortField);
            setSortDir('asc');
        }
    };

    const handleEdit = (definition: ContactAttributeDefinition) => {
        setSelectedDefinition(definition);
        openEditModal();
    };

    const handleDelete = (definition: ContactAttributeDefinition) => {
        deleteMutation.mutate({definitionId: definition.id}, {
            onSuccess: () => showSuccess(t`Attribute definition deleted successfully`),
            onError: (err: any) => {
                const message = err?.response?.data?.message;
                showError(message || t`Something went wrong while deleting the attribute definition`);
            },
        });
    };

    const handleOpenLinkedQuestions = (definition: ContactAttributeDefinition) => {
        setDrawerDefinition(definition);
        openDrawer();
    };

    const filteredSorted = useMemo(() => {
        const all = definitionsQuery.data?.data ?? [];
        const needle = query.trim().toLowerCase();
        let filtered = needle
            ? all.filter((d) =>
                d.label.toLowerCase().includes(needle) ||
                d.name.toLowerCase().includes(needle) ||
                d.type.toLowerCase().includes(needle),
            )
            : [...all];
        if (eventFilterDefinitionIds) {
            filtered = filtered.filter((d) => eventFilterDefinitionIds.has(Number(d.id)));
        }
        filtered.sort((a, b) => {
            if (sortBy === 'linked_event_count') {
                const av = Number(a.linked_event_count ?? 0);
                const bv = Number(b.linked_event_count ?? 0);
                const cmp = av - bv;
                return sortDir === 'asc' ? cmp : -cmp;
            }
            const av = String(a[sortBy as keyof ContactAttributeDefinition] ?? '');
            const bv = String(b[sortBy as keyof ContactAttributeDefinition] ?? '');
            const cmp = av.localeCompare(bv);
            return sortDir === 'asc' ? cmp : -cmp;
        });
        return filtered;
    }, [definitionsQuery.data, query, sortBy, sortDir, eventFilterDefinitionIds]);

    return (
        <>
            <Card>
                <Group gap="sm" wrap="wrap" mb="md" align="center">
                    <TextInput
                        placeholder={t`Search by label, name, or type...`}
                        leftSection={<IconSearch size={16}/>}
                        value={query}
                        onChange={(e) => setQuery(e.currentTarget.value)}
                        size="sm"
                        style={{flex: 1, minWidth: 220, marginBottom: 0}}
                    />
                    <Select
                        placeholder={t`Filter by event`}
                        data={eventOptions}
                        value={eventFilter}
                        onChange={setEventFilter}
                        clearable
                        searchable
                        size="sm"
                        style={{width: 240, marginBottom: 0}}
                    />
                    <Button onClick={openCreateModal} size="sm">
                        {t`Add Attribute`}
                    </Button>
                </Group>

                {definitionsQuery.isLoading && <TableSkeleton isVisible/>}

                {!!definitionsQuery.error && (
                    <Alert color="red" radius="md">
                        {t`Failed to load contact attributes`}
                    </Alert>
                )}

                {!definitionsQuery.isLoading && !definitionsQuery.error && filteredSorted.length === 0 && (
                    <Text c="dimmed" ta="center" py="xl">
                        {query || eventFilter
                            ? t`No matching attributes.`
                            : t`No contact attribute definitions have been added.`}
                    </Text>
                )}

                {filteredSorted.length > 0 && (
                    <Table striped highlightOnHover>
                        <Table.Thead>
                            <Table.Tr>
                                <SortableTh label={t`Label`} field="label" sortBy={sortBy} sortDir={sortDir} onSort={handleSort}/>
                                <SortableTh label={t`Name`} field="name" sortBy={sortBy} sortDir={sortDir} onSort={handleSort}/>
                                <SortableTh label={t`Type`} field="type" sortBy={sortBy} sortDir={sortDir} onSort={handleSort}/>
                                <SortableTh label={t`Used in`} field="linked_event_count" sortBy={sortBy} sortDir={sortDir} onSort={handleSort}/>
                                <SortableTh label={t`Status`} field="is_active" sortBy={sortBy} sortDir={sortDir} onSort={handleSort}/>
                                <Table.Th/>
                            </Table.Tr>
                        </Table.Thead>
                        <Table.Tbody>
                            {filteredSorted.map((definition) => {
                                const eventCount = Number(definition.linked_event_count ?? 0);
                                const questionCount = Number(definition.linked_question_count ?? 0);
                                return (
                                    <Table.Tr key={definition.id}>
                                        <Table.Td>{definition.label}</Table.Td>
                                        <Table.Td><code>{definition.name}</code></Table.Td>
                                        <Table.Td>{definition.type}</Table.Td>
                                        <Table.Td>
                                            {questionCount > 0 ? (
                                                <UnstyledButton
                                                    onClick={() => handleOpenLinkedQuestions(definition)}
                                                    aria-label={t`View linked questions`}
                                                >
                                                    <Badge
                                                        size="sm"
                                                        variant="light"
                                                        color="blue"
                                                        leftSection={<IconLink size={12}/>}
                                                        style={{cursor: 'pointer'}}
                                                    >
                                                        {eventCount === 1
                                                            ? t`${eventCount} event (${questionCount})`
                                                            : t`${eventCount} events (${questionCount})`}
                                                    </Badge>
                                                </UnstyledButton>
                                            ) : (
                                                <Text size="xs" c="dimmed">{t`Not linked`}</Text>
                                            )}
                                        </Table.Td>
                                        <Table.Td>
                                            <Badge color={definition.is_active ? 'green' : 'gray'} variant="light">
                                                {definition.is_active ? t`Active` : t`Inactive`}
                                            </Badge>
                                        </Table.Td>
                                        <Table.Td>
                                            <Menu shadow="md" width={200}>
                                                <Menu.Target>
                                                    <ActionIcon variant="subtle">
                                                        <IconDotsVertical size={14}/>
                                                    </ActionIcon>
                                                </Menu.Target>
                                                <Menu.Dropdown>
                                                    <Menu.Item leftSection={<IconPencil size={14}/>} onClick={() => handleEdit(definition)}>
                                                        {t`Edit`}
                                                    </Menu.Item>
                                                    <Menu.Item color="red" leftSection={<IconTrash size={14}/>} onClick={() => handleDelete(definition)}>
                                                        {t`Delete`}
                                                    </Menu.Item>
                                                </Menu.Dropdown>
                                            </Menu>
                                        </Table.Td>
                                    </Table.Tr>
                                );
                            })}
                        </Table.Tbody>
                    </Table>
                )}
            </Card>

            {createModalOpen && <CreateContactAttributeDefinitionModal onClose={closeCreateModal}/>}
            {editModalOpen && selectedDefinition && (
                <EditContactAttributeDefinitionModal definition={selectedDefinition} onClose={closeEditModal}/>
            )}
            <LinkedQuestionsDrawer
                definition={drawerDefinition}
                opened={drawerOpen}
                onClose={closeDrawer}
            />
        </>
    );
};

const useEventFilteredDefinitionIds = (eventId: string | null): Set<number> | null => {
    const eventQuestions = useQuery({
        queryKey: [GET_EVENT_QUESTIONS_QUERY_KEY, eventId],
        queryFn: async () => {
            const {data} = await questionClient.all(Number(eventId));
            return data;
        },
        enabled: !!eventId,
    });

    return useMemo(() => {
        if (!eventId) return null;
        const questions = eventQuestions.data ?? [];
        const ids = new Set<number>();
        for (const q of questions) {
            if (q.contact_attribute_definition_id) {
                ids.add(Number(q.contact_attribute_definition_id));
            }
        }
        return ids;
    }, [eventId, eventQuestions.data]);
};
