import {Container, Title, TextInput, Skeleton, Pagination, Stack} from "@mantine/core";
import {t} from "@lingui/macro";
import {IconSearch} from "@tabler/icons-react";
import {useState, useEffect} from "react";
import {useGetAllUsers} from "../../../../queries/useGetAllUsers";
import {useStartImpersonation} from "../../../../mutations/useStartImpersonation";
import {useResetUserTwoFactor} from "../../../../mutations/useResetUserTwoFactor";
import {confirmationDialog} from "../../../../utilites/confirmationDialog";
import {AdminUser} from "../../../../api/admin.client";
import AdminUsersTable from "../../../common/AdminUsersTable";
import {showError, showSuccess} from "../../../../utilites/notifications";
import {IdParam} from "../../../../types";
import {useNavigate} from "react-router";

const Users = () => {
    const navigate = useNavigate();
    const [page, setPage] = useState(1);
    const [search, setSearch] = useState("");
    const [debouncedSearch, setDebouncedSearch] = useState("");

    const {data: usersData, isLoading} = useGetAllUsers({
        page,
        per_page: 20,
        search: debouncedSearch
    });

    const startImpersonationMutation = useStartImpersonation();
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
                        onImpersonate={handleImpersonate}
                        onResetTwoFactor={handleResetTwoFactor}
                        isLoading={startImpersonationMutation.isPending}
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
