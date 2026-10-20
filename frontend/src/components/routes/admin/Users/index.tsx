import {Container, Title, TextInput, Skeleton, Pagination, Stack} from "@mantine/core";
import {t} from "@lingui/macro";
import {IconSearch} from "@tabler/icons-react";
import {useState, useEffect} from "react";
import {useGetAllUsers} from "../../../../queries/useGetAllUsers";
import {useImpersonateUser} from "../../../../hooks/useImpersonateUser";
import {useResetUserTwoFactor} from "../../../../mutations/useResetUserTwoFactor";
import {confirmationDialog} from "../../../../utilites/confirmationDialog";
import {AdminUser} from "../../../../api/admin.client";
import AdminUsersTable from "../../../common/AdminUsersTable";
import {showError, showSuccess} from "../../../../utilites/notifications";
import {IdParam} from "../../../../types";

const Users = () => {
    const [page, setPage] = useState(1);
    const [search, setSearch] = useState("");
    const [debouncedSearch, setDebouncedSearch] = useState("");

    const {data: usersData, isLoading} = useGetAllUsers({
        page,
        per_page: 20,
        search: debouncedSearch
    });

    const {impersonate, isPending: isImpersonating} = useImpersonateUser();
    const resetTwoFactorMutation = useResetUserTwoFactor();

    const handleResetTwoFactor = (user: AdminUser) => {
        confirmationDialog(
            t`Turn off two-factor authentication for ${user.email}? Only do this after confirming their identity. They will be emailed and can sign in with just their password.`,
            () => resetTwoFactorMutation.mutate(user.id as IdParam, {
                onSuccess: () => showSuccess(t`Two-factor authentication reset`),
                onError: (error: any) => showError(error?.response?.data?.message ?? t`Something went wrong. Please try again.`),
            }),
            {confirm: t`Reset 2FA`},
        );
    };

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
                <Title order={1}>{t`Users`}</Title>

                <TextInput
                    placeholder={t`Search by name, email, or account...`}
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
                    <AdminUsersTable
                        users={usersData?.data || []}
                        onImpersonate={impersonate}
                        onResetTwoFactor={handleResetTwoFactor}
                        isLoading={isImpersonating}
                    />
                )}

                {usersData?.meta && usersData.meta.last_page > 1 && (
                    <Pagination
                        total={usersData.meta.last_page}
                        value={page}
                        onChange={setPage}
                        mt="md"
                    />
                )}
            </Stack>
        </Container>
    );
};

export default Users;
