import {useParams} from "react-router";
import {CreateButton} from "../../common/CreateButton";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {PageTitle} from "../../common/PageTitle";
import {PageBody} from "../../common/PageBody";
import {PromoCodeTable} from "../../common/PromoCodeTable";
import {SearchBarWrapper} from "../../common/SearchBar";
import {useDisclosure} from "@mantine/hooks";
import {TableCard, TableCardFooter, TableCardSkeleton} from "../../common/TableCard";
import {tableSortFromQuery} from "../../common/TanStackTable/sorting.tsx";
import {IconPlus} from "@tabler/icons-react";
import {ToolBar} from "../../common/ToolBar";
import {useGetEventPromoCodes} from "../../../queries/useGetEventPromoCodes.ts";
import {CreatePromoCodeModal} from "../../modals/CreatePromoCodeModal";
import {useFilterQueryParamSync} from "../../../hooks/useFilterQueryParamSync.ts";
import {QueryFilters} from "../../../types.ts";
import {t} from "@lingui/macro";
import {SortSelector} from "../../common/SortSelector";

export const PromoCodes = () => {
    const {eventId} = useParams();
    const {data: event} = useGetEvent(eventId);
    const [searchParams, setSearchParams] = useFilterQueryParamSync();
    const promoCodesQuery = useGetEventPromoCodes(eventId, searchParams as QueryFilters);
    const promoCodes = promoCodesQuery?.data?.data;
    const pagination = promoCodesQuery?.data?.meta;
    const [createModalOpen, {open: openCreateModal, close: closeCreateModal}] = useDisclosure(false);

    return (
        <>
            <PageBody>
                <PageTitle
                    subheading={t`Create discounts, access codes for hidden tickets, and special offers.`}
                >{t`Promo Codes`}</PageTitle>
                <TableCard>
                <ToolBar
                    embedded
                    searchComponent={() => (
                        <SearchBarWrapper
                            placeholder={t`Search by name...`}
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
                    <CreateButton color="green" onClick={openCreateModal} leftSection={<IconPlus size={16}/>} data-testid="promo-code-create-button">
                        {t`Create`}
                    </CreateButton>
                </ToolBar>

                {(!promoCodes || !event) && <TableCardSkeleton rows={5}/>}

                {(promoCodes && event) &&
                    <PromoCodeTable
                        openCreateModal={openCreateModal}
                        event={event}
                        promoCodes={promoCodes}
                        isRefreshing={promoCodesQuery.isFetching}
                        sort={tableSortFromQuery(searchParams, setSearchParams, pagination)}
                    />}

                {!!promoCodes?.length && (
                    <TableCardFooter
                        pagination={pagination}
                        page={searchParams.pageNumber}
                        onPageChange={(value) => setSearchParams({pageNumber: value})}
                    />
                )}
                </TableCard>
            </PageBody>
            {createModalOpen && <CreatePromoCodeModal onClose={closeCreateModal} isOpen/>}
        </>
    );
};

export default PromoCodes;
