import {useParams} from "react-router-dom";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {PageTitle} from "../../common/PageTitle";
import {PageBody} from "../../common/PageBody";
import {PromoCodeTable} from "../../common/PromoCodeTable";
import {SearchBarWrapper} from "../../common/SearchBar";
import {useDisclosure} from "@mantine/hooks";
import {Pagination} from "../../common/Pagination";
import {Button} from "@mantine/core";
import {IconPlus} from "@tabler/icons-react";
import {ToolBar} from "../../common/ToolBar";
import {useGetEventPromoCodes} from "../../../queries/useGetEventPromoCodes.ts";
import {CreatePromoCodeModal} from "../../modals/CreatePromoCodeModal";
import {useFilterQueryParamSync} from "../../../hooks/useFilterQueryParamSync.ts";
import {QueryFilters} from "../../../types.ts";
import {TableSkeleton} from "../../common/TableSkeleton";
import {t} from "@lingui/macro";

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
                <PageTitle>{t`Promo Codes`}</PageTitle>
                <ToolBar searchComponent={() => (
                    <SearchBarWrapper
                        placeholder={t`Search by name...`}
                        setSearchParams={setSearchParams}
                        searchParams={searchParams}
                        pagination={pagination}
                    />
                )}>
                    <Button color={'green'} size={'sm'} onClick={openCreateModal} rightSection={<IconPlus/>}>
                        Create
                    </Button>

                </ToolBar>

                <TableSkeleton isVisible={!promoCodes || !event}/>

                {(promoCodes && event) &&
                    <PromoCodeTable
                        openCreateModal={openCreateModal}
                        event={event}
                        promoCodes={promoCodes}
                    />}

                {!!promoCodes?.length && (
                    <Pagination
                        value={searchParams.pageNumber}
                        onChange={(value) => setSearchParams({pageNumber: value})}
                        total={Number(pagination?.last_page)}
                    />
                )}

            </PageBody>
            {createModalOpen && <CreatePromoCodeModal onClose={closeCreateModal} isOpen/>}
        </>
    );
};

export default PromoCodes;