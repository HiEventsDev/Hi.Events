import {Container, Title, TextInput, Skeleton, Pagination, Stack} from "@mantine/core";
import {t} from "@lingui/macro";
import {IconSearch} from "@tabler/icons-react";
import {useState, useEffect} from "react";
import {useGetAllAccounts} from "../../../../queries/useGetAllAccounts";
import AdminAccountsTable from "../../../common/AdminAccountsTable";

const Accounts = () => {
    const [page, setPage] = useState(1);
    const [search, setSearch] = useState("");
    const [debouncedSearch, setDebouncedSearch] = useState("");

    const {data: accountsData, isLoading} = useGetAllAccounts({
        page,
        per_page: 20,
        search: debouncedSearch
    });

    useEffect(() => {
        const timer = setTimeout(() => {
            setDebouncedSearch(search);
            setPage(1);
        }, 500);

        return () => clearTimeout(timer);
    }, [search]);

    return (
        <Container size="xl" p="xl">
            <Stack gap="lg">
                <Title order={1}>{t`Accounts`}</Title>

                <TextInput
                    placeholder={t`Search by account name or email...`}
                    leftSection={<IconSearch size={16} />}
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                />

                {isLoading ? (
                    <Stack gap="md">
                        <Skeleton height={180} radius="md" />
                        <Skeleton height={180} radius="md" />
                        <Skeleton height={180} radius="md" />
                    </Stack>
                ) : (
                    <AdminAccountsTable accounts={accountsData?.data || []} />
                )}

                {accountsData?.meta && accountsData.meta.last_page > 1 && (
                    <Pagination
                        total={accountsData.meta.last_page}
                        value={page}
                        onChange={setPage}
                        mt="md"
                    />
                )}
            </Stack>
        </Container>
    );
};

export default Accounts;
