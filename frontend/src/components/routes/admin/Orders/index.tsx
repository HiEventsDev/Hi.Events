import {Container, Title, TextInput, Skeleton, Pagination, Stack} from "@mantine/core";
import {t} from "@lingui/macro";
import {IconSearch} from "@tabler/icons-react";
import {useState, useEffect} from "react";
import {useGetAllAdminOrders} from "../../../../queries/useGetAllAdminOrders.ts";
import AdminOrdersTable from "../../../common/AdminOrdersTable";

const Orders = () => {
    const [page, setPage] = useState(1);
    const [search, setSearch] = useState("");
    const [debouncedSearch, setDebouncedSearch] = useState("");
    const [sortBy, setSortBy] = useState("created_at");
    const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>("desc");

    const {data: ordersData, isLoading} = useGetAllAdminOrders({
        page,
        per_page: 20,
        search: debouncedSearch,
        sort_by: sortBy,
        sort_direction: sortDirection,
    });

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

    return (
        <Container size="xl" p="xl">
            <Stack gap="lg">
                <Title order={1}>{t`Orders`}</Title>

                <TextInput
                    placeholder={t`Search by order ID, customer name, or email...`}
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
                    <AdminOrdersTable
                        orders={ordersData?.data || []}
                        onSort={handleSort}
                        sortBy={sortBy}
                        sortDirection={sortDirection}
                    />
                )}

                {ordersData?.meta && ordersData.meta.last_page > 1 && (
                    <Pagination
                        total={ordersData.meta.last_page}
                        value={page}
                        onChange={setPage}
                        mt="md"
                    />
                )}
            </Stack>
        </Container>
    );
};

export default Orders;
