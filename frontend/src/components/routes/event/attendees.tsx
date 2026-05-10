import {useParams} from "react-router";
import {useGetAttendees} from "../../../queries/useGetAttendees.ts";
import {PageTitle} from "../../common/PageTitle";
import {PageBody} from "../../common/PageBody";
import {AttendeeTable} from "../../common/AttendeeTable";
import {SearchBarWrapper} from "../../common/SearchBar";
import {Pagination} from "../../common/Pagination";
import {Button, Group} from "@mantine/core";
import {IconDownload, IconPlus} from "@tabler/icons-react";
import {ToolBar} from "../../common/ToolBar";
import {TableSkeleton} from "../../common/TableSkeleton";
import {useFilterQueryParamSync} from "../../../hooks/useFilterQueryParamSync.ts";
import {EventType, IdParam, ProductType, QueryFilterOperator, QueryFilters} from "../../../types.ts";
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
import {useGetEventOccurrences} from "../../../queries/useGetEventOccurrences.ts";
import {SortSelector} from "../../common/SortSelector";
import {OccurrenceSelect} from "../../common/OccurrenceSelect";

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
    const isRecurring = event?.type === EventType.RECURRING;
    const {data: occurrencesData} = useGetEventOccurrences(eventId, {pageNumber: 1, perPage: 100} as QueryFilters);

    const occurrences = occurrencesData?.data || [];
    const occurrenceFilter = searchParams.filterFields?.event_occurrence_id;
    const selectedOccurrenceId = (occurrenceFilter && !Array.isArray(occurrenceFilter) ? String(occurrenceFilter.value) : null);

    const productOptions = getProductsFromEvent(event)
        ?.filter(product => product.product_type === ProductType.Ticket)
        ?.flatMap(product => {
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

    const handleOccurrenceChange = (value: string | null) => {
        const filterFields = {...(searchParams.filterFields || {})};
        if (value) {
            filterFields.event_occurrence_id = {operator: QueryFilterOperator.Equals, value};
        } else {
            delete filterFields.event_occurrence_id;
        }
        setSearchParams({
            ...searchParams,
            filterFields,
            pageNumber: 1,
        } as QueryFilters, true);
    };

    const handleFilterChange = (values: Record<string, any>) => {
        const filterFields: any = {};

        if (selectedOccurrenceId) {
            filterFields.event_occurrence_id = {operator: QueryFilterOperator.Equals, value: selectedOccurrenceId};
        }

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
        const filterFields: any = {};
        if (selectedOccurrenceId) {
            filterFields.event_occurrence_id = {operator: QueryFilterOperator.Equals, value: selectedOccurrenceId};
        }
        setSearchParams({
            ...searchParams,
            filterFields,
            pageNumber: 1
        } as QueryFilters, true);
    };

    const handleExport = async (eventId: IdParam) => {
        const occurrenceId = selectedOccurrenceId ? Number(selectedOccurrenceId) : null;
        await withLoadingNotification(async () => {
                setDownloadPending(true);
                const blob = await attendeesClient.export(eventId, occurrenceId);
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
        status: getFilterValue(searchParams.filterFields?.status),
    };

    return (
        <>
            <PageBody>
                <PageTitle
                    subheading={t`View, edit, and export your registered attendees.`}
                >
                    {t`Attendees`}
                </PageTitle>

                <ToolBar
                    searchComponent={() => (
                        <SearchBarWrapper
                            placeholder={t`Search by attendee name, email or order #...`}
                            setSearchParams={setSearchParams}
                            searchParams={searchParams}
                        />
                    )}
                    filterComponent={
                        <Group gap="sm" wrap="wrap">
                            {pagination?.allowed_sorts && (
                                <SortSelector
                                    selected={searchParams.sortBy && searchParams.sortDirection
                                        ? searchParams.sortBy + ':' + searchParams.sortDirection
                                        : pagination.default_sort + ':' + pagination.default_sort_direction}
                                    options={pagination.allowed_sorts}
                                    onSortSelect={(key, sortDirection) => {
                                        setSearchParams({sortBy: key, sortDirection});
                                    }}
                                />
                            )}
                            {isRecurring && occurrences.length > 0 && event?.timezone && (
                                <OccurrenceSelect
                                    occurrences={occurrences}
                                    timezone={event.timezone}
                                    value={selectedOccurrenceId}
                                    onChange={handleOccurrenceChange}
                                    placeholder={t`All Dates`}
                                    clearable
                                    size="sm"
                                />
                            )}
                            <FilterModal
                                filters={filterOptions}
                                activeFilters={currentFilters}
                                onChange={handleFilterChange}
                                onReset={handleResetFilters}
                                title={t`Filter Attendees`}
                            />
                        </Group>
                    }
                    resultCount={pagination?.total}
                    resultLabel={t`attendees`}
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
                                                 occurrenceId={selectedOccurrenceId ?? undefined}
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
