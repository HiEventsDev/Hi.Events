import {useParams} from "react-router";
import {useGetAttendees} from "../../../queries/useGetAttendees.ts";
import {PageTitle} from "../../common/PageTitle";
import {PageBody} from "../../common/PageBody";
import {AttendeeTable} from "../../common/AttendeeTable";
import {SearchBarWrapper} from "../../common/SearchBar";
import {Pagination} from "../../common/Pagination";
import {Button} from "@mantine/core";
import {IconDownload, IconPlus} from "@tabler/icons-react";
import {ToolBar} from "../../common/ToolBar";
import {TableSkeleton} from "../../common/TableSkeleton";
import {useFilterQueryParamSync} from "../../../hooks/useFilterQueryParamSync.ts";
import {IdParam, QueryFilters, QueryFilterOperator} from "../../../types.ts";
import {useDisclosure} from "@mantine/hooks";
import {CreateAttendeeModal} from "../../modals/CreateAttendeeModal";
import {downloadBinary} from "../../../utilites/download.ts";
import {attendeesClient} from "../../../api/attendee.client.ts";
import {useState} from "react";
import {t} from "@lingui/macro";
import {withLoadingNotification} from "../../../utilites/withLoadingNotification.tsx";
import {FilterModal, FilterOption} from "../../common/FilterModal";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {getProductsFromEvent} from "../../../utilites/helpers.ts";

const attendeeStatuses = [
    {label: t`Active`, value: 'ACTIVE'},
    {label: t`Cancelled`, value: 'CANCELLED'},
    {label: t`Awaiting Payment`, value: 'AWAITING_PAYMENT'},
];

const Attendees = () => {
    const {eventId} = useParams();
    const [searchParams, setSearchParams] = useFilterQueryParamSync();
    const attendeesQuery = useGetAttendees(eventId, searchParams as QueryFilters);
    const attendees = attendeesQuery?.data?.data;
    const pagination = attendeesQuery?.data?.meta;
    const [createModalOpen, {open: openCreateModal, close: closeCreateModal}] = useDisclosure(false);
    const [downloadPending, setDownloadPending] = useState(false);
    const {data: event} = useGetEvent(eventId);

    const productOptions = getProductsFromEvent(event)?.flatMap(product => {
        const options = [];

        options.push({
            label: product.title,
            value: `product:${product.id}`
        });

        if (product.type === 'TIERED' && product.prices) {
            product.prices.forEach(price => {
                options.push({
                    label: `${product.title} - ${price.label}`,
                    value: `tier:${price.id}`
                });
            });
        }

        return options;
    }) || [];

    const filterOptions: FilterOption[] = [
        {
            field: 'product_id',
            label: t`Ticket Type`,
            type: 'multi-select',
            options: productOptions
        },
        {
            field: 'status',
            label: t`Attendee Status`,
            type: 'multi-select',
            options: attendeeStatuses
        }
    ];

    const handleFilterChange = (values: Record<string, any>) => {
        const filterFields: any = {};

        if (values.product_id?.length > 0) {
            const productIds: string[] = [];
            const tierIds: string[] = [];

            values.product_id.forEach((value: string) => {
                if (value.startsWith('product:')) {
                    productIds.push(value.replace('product:', ''));
                } else if (value.startsWith('tier:')) {
                    tierIds.push(value.replace('tier:', ''));
                }
            });

            if (productIds.length > 0) {
                filterFields.product_id = {operator: QueryFilterOperator.In, value: productIds};
            }
            if (tierIds.length > 0) {
                filterFields.product_price_id = {operator: QueryFilterOperator.In, value: tierIds};
            }
        }
        if (values.status?.length > 0) {
            filterFields.status = {operator: QueryFilterOperator.In, value: values.status};
        }

        setSearchParams({
            ...searchParams,
            filterFields,
            pageNumber: 1
        } as QueryFilters, true);
    };

    const handleResetFilters = () => {
        setSearchParams({
            ...searchParams,
            filterFields: {},
            pageNumber: 1
        } as QueryFilters, true);
    };

    const handleExport = async (eventId: IdParam) => {
        await withLoadingNotification(async () => {
                setDownloadPending(true);
                const blob = await attendeesClient.export(eventId);
                downloadBinary(blob, 'attendees.xlsx');
            },
            {
                loading: {
                    title: t`Exporting Attendees`,
                    message: t`Please wait while we prepare your attendees for export...`
                },
                success: {
                    title: t`Attendees Exported`,
                    message: t`Your attendees have been exported successfully.`,
                    onRun: () => setDownloadPending(false)
                },
                error: {
                    title: t`Failed to export attendees`,
                    message: t`Please try again.`,
                    onRun: () => setDownloadPending(false)
                }
            });
    };

    const getFilterValue = (field: any): string[] => {
        if (!field) return [];
        if (Array.isArray(field)) return field;
        if (Array.isArray(field.value)) return field.value;
        return field.value ? [field.value] : [];
    };

    const getProductFilterValues = (): string[] => {
        const values: string[] = [];

        const productIds = getFilterValue(searchParams.filterFields?.product_id);
        const tierIds = getFilterValue(searchParams.filterFields?.product_price_id);

        productIds.forEach(id => values.push(`product:${id}`));
        tierIds.forEach(id => values.push(`tier:${id}`));

        return values;
    };

    const currentFilters = {
        product_id: getProductFilterValues(),
        status: getFilterValue(searchParams.filterFields?.status)
    };

    return (
        <>
            <PageBody>
                <PageTitle>
                    {t`Attendees`}
                </PageTitle>

                <ToolBar
                    filterComponent={
                        <FilterModal
                            filters={filterOptions}
                            activeFilters={currentFilters}
                            onChange={handleFilterChange}
                            onReset={handleResetFilters}
                            title={t`Filter Attendees`}
                        />
                    }
                    searchComponent={() => (
                        <SearchBarWrapper
                            placeholder={t`Search by attendee name, email or order #...`}
                            setSearchParams={setSearchParams}
                            searchParams={searchParams}
                            pagination={pagination}
                        />
                    )}
                >
                    <Button color={'green'} size={'sm'} onClick={openCreateModal} rightSection={<IconPlus/>}>
                        {t`Create`}
                    </Button>

                    <Button color={'green'}
                            size={'sm'}
                            loading={downloadPending}
                            onClick={() => handleExport(eventId)}
                            rightSection={<IconDownload/>}
                    >
                        {t`Export`}
                    </Button>
                </ToolBar>

                <TableSkeleton isVisible={!attendees || attendeesQuery.isFetching}/>

                {(!!attendees) && <AttendeeTable openCreateModal={openCreateModal}
                                                 attendees={attendees}
                />}

                {!!attendees?.length
                    && <Pagination value={searchParams.pageNumber}
                                   onChange={(value) => setSearchParams({pageNumber: value})}
                                   total={Number(pagination?.last_page)}/>}
            </PageBody>
            {createModalOpen && <CreateAttendeeModal onClose={closeCreateModal} isOpen={createModalOpen}/>}
        </>
    );
};

export default Attendees;
