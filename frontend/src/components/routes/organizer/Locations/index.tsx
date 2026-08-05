import {t} from "@lingui/macro";
import {Button} from "@mantine/core";
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
import {Pagination} from "../../../common/Pagination";
import {TableSkeleton} from "../../../common/TableSkeleton";
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
            <ToolBar
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
                resultCount={pagination?.total}
                resultLabel={t`locations`}
            >
                <Button color={'green'} size={'sm'} onClick={openCreateModal} rightSection={<IconPlus/>}>
                    {t`Add Location`}
                </Button>
            </ToolBar>

            <TableSkeleton isVisible={!locations}/>

            {locations && (
                <LocationsTable
                    locations={locations}
                    openCreateModal={openCreateModal}
                />
            )}

            {!!locations?.length && (
                <Pagination
                    value={searchParams.pageNumber}
                    onChange={(value) => setSearchParams({pageNumber: value})}
                    total={Number(pagination?.last_page)}
                />
            )}

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
