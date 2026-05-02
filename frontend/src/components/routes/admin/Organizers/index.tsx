import {Alert, Button, Container, Group, Pagination, Skeleton, Stack, TextInput, Title} from "@mantine/core";
import {useDisclosure} from "@mantine/hooks";
import {t} from "@lingui/macro";
import {IconInfoCircle, IconPlus, IconSearch} from "@tabler/icons-react";
import {useEffect, useState} from "react";
import {useQueryClient} from "@tanstack/react-query";
import {useGetAllOrganizers, GET_ALL_ORGANIZERS_QUERY_KEY} from "../../../../queries/useGetAllOrganizers";
import AdminOrganizersTable from "../../../common/AdminOrganizersTable";
import {CreateOrganizerModal} from "../../../modals/CreateOrganizerModal";

const Organizers = () => {
    const [page, setPage] = useState(1);
    const [search, setSearch] = useState("");
    const [debouncedSearch, setDebouncedSearch] = useState("");
    const [createOpened, {open: openCreate, close: closeCreate}] = useDisclosure(false);
    const queryClient = useQueryClient();

    const {data: organizersData, isLoading} = useGetAllOrganizers({
        page,
        per_page: 20,
        search: debouncedSearch,
    });

    useEffect(() => {
        const timer = setTimeout(() => {
            setDebouncedSearch(search);
            setPage(1);
        }, 500);

        return () => clearTimeout(timer);
    }, [search]);

    const handleCreateClose = () => {
        closeCreate();
        queryClient.invalidateQueries({queryKey: [GET_ALL_ORGANIZERS_QUERY_KEY]});
    };

    return (
        <Container size="xl" p="xl">
            <Stack gap="lg">
                <Group justify="space-between" align="center">
                    <Title order={1}>{t`Organizers`}</Title>
                    <Button leftSection={<IconPlus size={16}/>} onClick={openCreate}>
                        {t`Create Organizer`}
                    </Button>
                </Group>

                <Alert icon={<IconInfoCircle size={16}/>} variant="light" color="blue">
                    {t`Creating from here adds the organizer to your own account. To create one inside another account, impersonate a user in that account first.`}
                </Alert>

                <TextInput
                    placeholder={t`Search by name, email, or account...`}
                    leftSection={<IconSearch size={16}/>}
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                />

                {isLoading ? (
                    <Stack gap="md">
                        <Skeleton height={180} radius="md"/>
                        <Skeleton height={180} radius="md"/>
                        <Skeleton height={180} radius="md"/>
                    </Stack>
                ) : (
                    <AdminOrganizersTable organizers={organizersData?.data || []}/>
                )}

                {organizersData?.meta && organizersData.meta.last_page > 1 && (
                    <Pagination
                        total={organizersData.meta.last_page}
                        value={page}
                        onChange={setPage}
                        mt="md"
                    />
                )}
            </Stack>

            {createOpened && <CreateOrganizerModal onClose={handleCreateClose}/>}
        </Container>
    );
};

export default Organizers;
