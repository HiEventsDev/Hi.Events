import {t} from "@lingui/macro";
import {useState} from "react";
import {Anchor, Badge, Drawer, Group, Stack, Table, Text, TextInput} from "@mantine/core";
import {IconExternalLink, IconSearch} from "@tabler/icons-react";
import {Link} from "react-router";
import {Pagination} from "../../../../common/Pagination";
import {TableSkeleton} from "../../../../common/TableSkeleton";
import {ContactAttributeDefinition, QueryFilters} from "../../../../../types.ts";
import {useGetLinkedQuestionsForDefinition} from "../../../../../queries/useGetLinkedQuestionsForDefinition.ts";

interface LinkedQuestionsDrawerProps {
    definition: ContactAttributeDefinition | undefined;
    opened: boolean;
    onClose: () => void;
}

export const LinkedQuestionsDrawer = ({definition, opened, onClose}: LinkedQuestionsDrawerProps) => {
    const [page, setPage] = useState(1);
    const [query, setQuery] = useState('');

    const params: QueryFilters = {
        pageNumber: page,
        perPage: 20,
        query: query || undefined,
    };

    const result = useGetLinkedQuestionsForDefinition(
        definition?.id,
        params,
        opened && definition !== undefined,
    );

    const rows = result.data?.data;
    const meta = result.data?.meta;

    return (
        <Drawer
            opened={opened}
            onClose={() => { setPage(1); setQuery(''); onClose(); }}
            position="right"
            size="lg"
            title={definition ? t`Questions linked to "${definition.label}"` : ''}
        >
            <Stack gap="md">
                <TextInput
                    placeholder={t`Search by question or event title...`}
                    leftSection={<IconSearch size={16}/>}
                    value={query}
                    onChange={(e) => { setQuery(e.currentTarget.value); setPage(1); }}
                    size="sm"
                />

                {result.isLoading && <TableSkeleton isVisible/>}

                {!result.isLoading && rows && rows.length === 0 && (
                    <Text c="dimmed" ta="center" py="xl">
                        {query ? t`No matching questions.` : t`No questions are linked to this attribute.`}
                    </Text>
                )}

                {rows && rows.length > 0 && (
                    <>
                        <Table striped highlightOnHover>
                            <Table.Thead>
                                <Table.Tr>
                                    <Table.Th>{t`Question`}</Table.Th>
                                    <Table.Th>{t`Event`}</Table.Th>
                                    <Table.Th>{t`Status`}</Table.Th>
                                    <Table.Th/>
                                </Table.Tr>
                            </Table.Thead>
                            <Table.Tbody>
                                {rows.map((row) => (
                                    <Table.Tr key={`${row.event_id}-${row.question_id}`}>
                                        <Table.Td>
                                            <Group gap={4}>
                                                <Text size="sm">{row.question_title}</Text>
                                                {row.question_required && (
                                                    <Badge size="xs" color="red" variant="light">{t`Required`}</Badge>
                                                )}
                                                {row.question_is_hidden && (
                                                    <Badge size="xs" color="gray" variant="light">{t`Hidden`}</Badge>
                                                )}
                                            </Group>
                                        </Table.Td>
                                        <Table.Td>
                                            <Text size="sm">{row.event_title}</Text>
                                            {row.event_start_date && (
                                                <Text size="xs" c="dimmed">
                                                    {new Date(row.event_start_date).toLocaleDateString()}
                                                </Text>
                                            )}
                                        </Table.Td>
                                        <Table.Td>
                                            <Badge size="xs" variant="light">{row.event_status || '-'}</Badge>
                                        </Table.Td>
                                        <Table.Td>
                                            <Anchor
                                                component={Link}
                                                to={`/manage/event/${row.event_id}/questions`}
                                                size="sm"
                                            >
                                                <Group gap={4}>
                                                    <IconExternalLink size={12}/>
                                                    {t`Open`}
                                                </Group>
                                            </Anchor>
                                        </Table.Td>
                                    </Table.Tr>
                                ))}
                            </Table.Tbody>
                        </Table>

                        {meta && Number(meta.last_page) > 1 && (
                            <Pagination value={page} onChange={setPage} total={Number(meta.last_page)}/>
                        )}
                    </>
                )}
            </Stack>
        </Drawer>
    );
};
