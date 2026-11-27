import {Container, Title, TextInput, Stack} from "@mantine/core";
import {t} from "@lingui/macro";
import {IconSearch} from "@tabler/icons-react";
import {useState, useEffect} from "react";
import {useGetAllAdminOrders} from "../../../../queries/useGetAllAdminOrders.ts";
import AdminOrdersTable from "../../../common/AdminOrdersTable";
import {TableCard, TableCardFooter, TableCardSkeleton} from "../../../common/TableCard";
import {ToolBar} from "../../../common/ToolBar";
import {TableSort} from "../../../common/TanStackTable/sorting.tsx";

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

    const sort: TableSort = {
        sortBy,
        sortDirection,
        onSort: (column, direction) => {
            setSortBy(column);
            setSortDirection(direction);
        },
    };

    return (
        <Container size="xl" p="xl">
            <Stack gap="lg">
                <Title order={1}>{t`Orders`}</Title>

                <TableCard>
                    <ToolBar
                        embedded
                        searchComponent={() => (
                            <TextInput
                                placeholder={t`Search by order ID, customer name, or email...`}
                                leftSection={<IconSearch size={16}/>}
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                            />
                        )}
                    />

                    {isLoading ? <TableCardSkeleton/> : (
                        <AdminOrdersTable
                            orders={ordersData?.data || []}
                            sort={sort}
                        />
                    )}

                    <TableCardFooter pagination={ordersData?.meta} page={page} onPageChange={setPage}/>
                </TableCard>
            </Stack>
        </Container>
    );
};

export default Orders;
