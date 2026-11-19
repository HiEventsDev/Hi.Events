import {useState} from "react";
import {t} from "@lingui/macro";
import {MultiSelect} from "@mantine/core";
import {useDebouncedValue} from "@mantine/hooks";
import {IdParam} from "../../../../types.ts";
import {useGetCrmSegmentOptions} from "../../queries/useGetCrmSegmentOptions.ts";
import {eventOptionLabel, productOptionLabel} from "../../utilites/segmentOptions.ts";

export const EntitySearchSelect = ({organizerId, entity, value, onChange}: {
    organizerId: IdParam,
    entity: 'event' | 'product',
    value: number[],
    onChange: (value: number[]) => void,
}) => {
    const [search, setSearch] = useState('');
    const [debouncedSearch] = useDebouncedValue(search.trim(), 300);
    const options = useGetCrmSegmentOptions(organizerId, entity === 'event'
        ? {event_query: debouncedSearch || undefined, event_ids: value}
        : {with_products: true, product_query: debouncedSearch || undefined, product_ids: value});

    const data = entity === 'event'
        ? (options.data?.events ?? []).map(event => ({value: String(event.id), label: eventOptionLabel(event)}))
        : (options.data?.products ?? []).map(product => ({value: String(product.id), label: productOptionLabel(product)}));

    return (
        <MultiSelect
            aria-label={t`Options`}
            placeholder={value.length === 0 ? (entity === 'event' ? t`Search events…` : t`Search ticket types or events…`) : undefined}
            data={data}
            value={value.map(String)}
            onChange={next => onChange(next.map(Number))}
            searchable
            searchValue={search}
            onSearchChange={setSearch}
            filter={({options: items}) => items}
            maxValues={50}
            nothingFoundMessage={options.isFetching ? t`Searching…` : t`Nothing matches`}
        />
    );
};
