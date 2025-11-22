import {Container, Title, TextInput, Skeleton, Pagination, Stack} from "@mantine/core";
import {t} from "@lingui/macro";
import {IconSearch} from "@tabler/icons-react";
import {useState, useEffect} from "react";
import {useNavigate} from "react-router";
import {useGetAllAdminEvents} from "../../../../queries/useGetAllAdminEvents.ts";
import {useStartImpersonation} from "../../../../mutations/useStartImpersonation";
import AdminEventsTable from "../../../common/AdminEventsTable";
import {showError, showSuccess} from "../../../../utilites/notifications";
import {IdParam} from "../../../../types";
import {AdminEvent} from "../../../../api/admin.client";
import {getConfig} from "../../../../utilites/config";

const Events = () => {
    const navigate = useNavigate();
    const [page, setPage] = useState(1);
    const [search, setSearch] = useState("");
    const [debouncedSearch, setDebouncedSearch] = useState("");
    const [sortBy, setSortBy] = useState("start_date");
    const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>("desc");

    const {data: eventsData, isLoading} = useGetAllAdminEvents({
        page,
        per_page: 20,
        search: debouncedSearch,
        sort_by: sortBy,
        sort_direction: sortDirection,
    });

    const startImpersonationMutation = useStartImpersonation();

    useEffect(() => {
        const timer = setTimeout(() => {
            setDebouncedSearch(search);
            setPage(1);
        }, 500);

        return () => clearTimeout(timer);
    }, [search]);

    const handleSort = (column: string) => {
        if (sortBy === column) {
            setSortDirection(sortDirection === 'asc' ? 'desc' : 'asc');
        } else {
            setSortBy(column);
            setSortDirection('desc');
        }
    };

    const handleViewEvent = (event: AdminEvent) => {
        const publicEventUrl = `${getConfig('VITE_FRONTEND_URL')}/event/${event.id}/${event.slug}`;
        window.open(publicEventUrl, '_blank');
    };

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
                <Title order={1}>{t`Events`}</Title>

                <TextInput
                    placeholder={t`Search by event title or organizer...`}
                    leftSection={<IconSearch size={16} />}
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                />

                {isLoading ? (
                    <Stack gap="md">
                        <Skeleton height={50} radius="md" />
                        <Skeleton height={400} radius="md" />
                    </Stack>
                ) : (
                    <AdminEventsTable
                        events={eventsData?.data || []}
                        onSort={handleSort}
                        sortBy={sortBy}
                        sortDirection={sortDirection}
                        onViewEvent={handleViewEvent}
                        onImpersonate={handleImpersonate}
                        isImpersonating={startImpersonationMutation.isPending}
                    />
                )}

                {eventsData?.meta && eventsData.meta.last_page > 1 && (
                    <Pagination
                        total={eventsData.meta.last_page}
                        value={page}
                        onChange={setPage}
                        mt="md"
                    />
                )}
            </Stack>
        </Container>
    );
};

export default Events;
