import {useCallback, useEffect, useState} from 'react';
import {useSearchParams} from 'react-router';
import {QueryFilters} from "../types";

const debounce = (func: Function, delay: number) => {
    let timerId: ReturnType<typeof setTimeout>;
    return (...args: any[]) => {
        if (timerId) clearTimeout(timerId);
        timerId = setTimeout(() => func(...args), delay);
    };
};

export const useFilterQueryParamSync = (): [
    Partial<QueryFilters>,
    (updates: Partial<QueryFilters>, replace?: boolean) => void
] => {
    const [searchParams, setSearchParams] = useSearchParams();
    const [queryParams, setQueryParams] = useState<Partial<QueryFilters>>({});

    useEffect(() => {
        const parsedParams: Partial<QueryFilters> = {};

        searchParams.forEach((value, key) => {
            if (key.startsWith('filterFields[')) {
                const match = key.match(/^filterFields\[(.+)\]\[(.+)\]$/);
                if (match) {
                    const [_, fieldName, operator] = match;
                    if (!parsedParams.filterFields) {
                        parsedParams.filterFields = {};
                    }
                    // @ts-ignore
                    parsedParams.filterFields[fieldName] = {
                        operator,
                        value: value.includes(',') ? value.split(',') : value
                    };
                }
            } else {
                // @ts-ignore - Handle non-filterFields params
                parsedParams[key] = value;
            }
        });

        setQueryParams(parsedParams);
    }, [searchParams]);

    const debouncedSetSearchParams = useCallback(
        debounce((params: URLSearchParams) => {
            setSearchParams(params);
        }, 300),
        [setSearchParams]
    );

    const updateSearchParams = useCallback(
        (updates: Partial<QueryFilters>, replace: boolean = false) => {
            const newParams = replace ? new URLSearchParams() : new URLSearchParams(searchParams);

            // Clear existing filter fields if replacing
            if (replace) {
                searchParams.forEach((_, key) => {
                    if (key.startsWith('filterFields[')) {
                        newParams.delete(key);
                    }
                });
            }

            // Update params
            Object.entries(updates).forEach(([key, value]) => {
                if (key === 'filterFields' && value) {
                    Object.entries(value).forEach(([field, condition]) => {
                        if (condition) {
                            const paramKey = `filterFields[${field}][${condition.operator}]`;
                            if (Array.isArray(condition.value)) {
                                newParams.set(paramKey, condition.value.join(','));
                            } else {
                                newParams.set(paramKey, String(condition.value));
                            }
                        }
                    });
                } else if (value !== undefined) {
                    newParams.set(key, String(value));
                }
            });

            debouncedSetSearchParams(newParams);
        },
        [searchParams, debouncedSetSearchParams]
    );

    return [queryParams, updateSearchParams];
};
