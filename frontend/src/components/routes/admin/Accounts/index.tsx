import {Container, Title, TextInput, Skeleton, Pagination, Stack} from "@mantine/core";
import {t} from "@lingui/macro";
import {IconSearch} from "@tabler/icons-react";
import {useState, useEffect} from "react";
import {useGetAllAccounts} from "../../../../queries/useGetAllAccounts";
import {useStartImpersonation} from "../../../../mutations/useStartImpersonation";
import AdminAccountsTable from "../../../common/AdminAccountsTable";
import {showError, showSuccess} from "../../../../utilites/notifications";
import {IdParam} from "../../../../types";
import {useNavigate} from "react-router";

const Accounts = () => {
    const navigate = useNavigate();
    const [page, setPage] = useState(1);
    const [search, setSearch] = useState("");
    const [debouncedSearch, setDebouncedSearch] = useState("");

    const {data: accountsData, isLoading} = useGetAllAccounts({
        page,
        per_page: 20,
        search: debouncedSearch
    });

    const startImpersonationMutation = useStartImpersonation();

    useEffect(() => {
        const timer = setTimeout(() => {
            setDebouncedSearch(search);
            setPage(1);
        }, 500);

        return () => clearTimeout(timer);
    }, [search]);

    const handleImpersonate = (userId: IdParam, accountId: IdParam) => {
        startImpersonationMutation.mutate({userId, accountId}, {
            onSuccess: (response) => {
                showSuccess(response.message || t`Impersonation started`);
                if (response.redirect_url) {
                    window.location.href = response.redirect_url;
                } else {
                    navigate('/manage/events');
                }
            },
            onError: (error: any) => {
                showError(
                    error?.response?.data?.message ||
                    t`Failed to start impersonation. Please try again.`
                );
            }
        });
    };

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
                    <AdminAccountsTable
                        accounts={accountsData?.data || []}
                        onImpersonate={handleImpersonate}
                        isLoading={startImpersonationMutation.isPending}
                    />
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
