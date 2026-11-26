import {t} from "@lingui/macro";
import {CreateButton} from "../../../common/CreateButton";
import {IconPlus} from "@tabler/icons-react";
import {useDisclosure} from "@mantine/hooks";
import {useParams} from "react-router";
import {useGetOrganizerLocations} from "../../../../queries/useGetOrganizerLocations.ts";
import {IdParam, QueryFilters} from "../../../../types.ts";
import {PageBody} from "../../../common/PageBody";
import {PageTitle} from "../../../common/PageTitle";
import {ToolBar} from "../../../common/ToolBar";
import {SearchBarWrapper} from "../../../common/SearchBar";
import {SortSelector} from "../../../common/SortSelector";
import {TableCard, TableCardFooter, TableCardSkeleton} from "../../../common/TableCard";
import {tableSortFromQuery} from "../../../common/TanStackTable/sorting.tsx";
import {LocationsTable} from "../../../common/LocationsTable";
import {useFilterQueryParamSync} from "../../../../hooks/useFilterQueryParamSync.ts";
import {LocationEditModal} from "../../../modals/LocationEditModal";

export default function Locations() {
    const {organizerId} = useParams();
    const [searchParams, setSearchParams] = useFilterQueryParamSync();
    const locationsQuery = useGetOrganizerLocations(organizerId as IdParam, searchParams as QueryFilters);
    const locations = locationsQuery.data?.data;
    const pagination = locationsQuery.data?.meta;
    const [createModalOpen, {open: openCreateModal, close: closeCreateModal}] = useDisclosure(false);

    return (
        <PageBody>
            <PageTitle
                subheading={t`Reusable venues for your events. Locations created from the autocomplete are saved here automatically.`}
            >{t`Locations`}</PageTitle>
            <TableCard>
            <ToolBar
                embedded
                searchComponent={() => (
                    <SearchBarWrapper
                        placeholder={t`Search by name or address...`}
                        setSearchParams={setSearchParams}
                        searchParams={searchParams}
                    />
                )}
                filterComponent={pagination?.allowed_sorts ? (
                    <SortSelector
                        selected={searchParams.sortBy && searchParams.sortDirection
                            ? searchParams.sortBy + ':' + searchParams.sortDirection
                            : pagination.default_sort + ':' + pagination.default_sort_direction}
                        options={pagination.allowed_sorts}
                        onSortSelect={(key, sortDirection) => {
                            setSearchParams({sortBy: key, sortDirection});
                        }}
                    />
                ) : undefined}
            >
                <CreateButton color="green" onClick={openCreateModal} leftSection={<IconPlus size={16}/>}>
                    {t`Add Location`}
                </CreateButton>
            </ToolBar>

            {!locations && <TableCardSkeleton rows={5}/>}

            {locations && (
                <LocationsTable
                    locations={locations}
                    openCreateModal={openCreateModal}
                    isRefreshing={locationsQuery.isFetching}
                    sort={tableSortFromQuery(searchParams, setSearchParams, pagination)}
                />
            )}

            {!!locations?.length && (
                <TableCardFooter
                    pagination={pagination}
                    page={searchParams.pageNumber}
                    onPageChange={(value) => setSearchParams({pageNumber: value})}
                />
            )}
            </TableCard>

            {createModalOpen && (
                <LocationEditModal
                    onClose={closeCreateModal}
                    organizerId={organizerId as IdParam}
                    location={null}
                />
            )}
        </PageBody>
    );
}
