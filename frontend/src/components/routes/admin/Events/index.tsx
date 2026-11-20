import {Container, Title, TextInput, Stack} from "@mantine/core";
import {t} from "@lingui/macro";
import {IconSearch} from "@tabler/icons-react";
import {useState, useEffect} from "react";
import {useNavigate} from "react-router";
import {useGetAllAdminEvents} from "../../../../queries/useGetAllAdminEvents.ts";
import {useStartImpersonation} from "../../../../mutations/useStartImpersonation";
import AdminEventsTable from "../../../common/AdminEventsTable";
import {TableCard, TableCardFooter, TableCardSkeleton} from "../../../common/TableCard";
import {ToolBar} from "../../../common/ToolBar";
import {TableSort} from "../../../common/TanStackTable/sorting.tsx";
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

    const sort: TableSort = {
        sortBy,
        sortDirection,
        onSort: (column, direction) => {
            setSortBy(column);
            setSortDirection(direction);
        },
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

                <TableCard>
                    <ToolBar
                        embedded
                        searchComponent={() => (
                            <TextInput
                                placeholder={t`Search by event title or organizer...`}
                                leftSection={<IconSearch size={16}/>}
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                            />
                        )}
                    />

                    {isLoading ? <TableCardSkeleton/> : (
                        <AdminEventsTable
                            events={eventsData?.data || []}
                            sort={sort}
                            onViewEvent={handleViewEvent}
                            onImpersonate={handleImpersonate}
                            isImpersonating={startImpersonationMutation.isPending}
                        />
                    )}

                    <TableCardFooter pagination={eventsData?.meta} page={page} onPageChange={setPage}/>
                </TableCard>
            </Stack>
        </Container>
    );
};

export default Events;
