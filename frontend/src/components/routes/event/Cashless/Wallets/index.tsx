import {t} from "@lingui/macro";
import {Button} from "@mantine/core";
import {IconCoin} from "@tabler/icons-react";
import {useDisclosure} from "@mantine/hooks";
import {useParams} from "react-router";
import {PageBody} from "../../../../common/PageBody";
import {PageTitle} from "../../../../common/PageTitle";
import {ToolBar} from "../../../../common/ToolBar";
import {SearchBarWrapper} from "../../../../common/SearchBar";
import {SortSelector} from "../../../../common/SortSelector";
import {TableSkeleton} from "../../../../common/TableSkeleton";
import {Pagination} from "../../../../common/Pagination";
import {CashlessWalletTable} from "../../../../common/CashlessWalletTable";
import {CashlessDisabledNotice} from "../CashlessDisabledNotice";
import {CashlessTicketLookupModal} from "../../../../modals/CashlessTicketLookupModal";
import {useFilterQueryParamSync} from "../../../../../hooks/useFilterQueryParamSync.ts";
import {useGetCashlessWallets} from "../../../../../queries/useGetCashlessWallets.ts";
import {useGetCashlessSettings} from "../../../../../queries/useGetCashlessSettings.ts";
import {QueryFilters} from "../../../../../types.ts";

const CashlessWallets = () => {
    const {eventId} = useParams();
    const [searchParams, setSearchParams] = useFilterQueryParamSync();
    const {data: settings} = useGetCashlessSettings(eventId);
    const {data: walletsData} = useGetCashlessWallets(eventId, searchParams as QueryFilters);
    const [lookupModalOpen, {open: openLookupModal, close: closeLookupModal}] = useDisclosure(false);

    const wallets = walletsData?.data;
    const pagination = walletsData?.meta;

    return (
        <PageBody>
            <PageTitle
                subheading={t`Every ticket carries a balance that attendees can spend at your bars and stands.`}
            >
                {t`Cashless Balances`}
            </PageTitle>

            {settings && !settings.cashless_enabled && <CashlessDisabledNotice/>}

            <ToolBar
                searchComponent={() => (
                    <SearchBarWrapper
                        placeholder={t`Search by name, email or ticket ID...`}
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
                        onSortSelect={(key, sortDirection) => setSearchParams({sortBy: key, sortDirection})}
                    />
                ) : undefined}
            >
                <Button
                    leftSection={<IconCoin/>}
                    color="green"
                    onClick={openLookupModal}
                    data-testid="cashless-ticket-topup-button"
                >
                    {t`Top up a ticket`}
                </Button>
            </ToolBar>

            {lookupModalOpen && <CashlessTicketLookupModal onClose={closeLookupModal}/>}

            <TableSkeleton isVisible={!wallets}/>

            {wallets && <CashlessWalletTable wallets={wallets}/>}

            {(!!wallets?.length && (pagination?.last_page || 0) > 1) && (
                <Pagination
                    value={searchParams.pageNumber}
                    onChange={(value) => setSearchParams({pageNumber: value})}
                    total={Number(pagination?.last_page)}
                />
            )}
        </PageBody>
    );
};

export default CashlessWallets;
