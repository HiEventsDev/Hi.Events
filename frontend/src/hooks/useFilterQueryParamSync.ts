import {useCallback, useEffect, useState} from 'react';
import {useSearchParams} from 'react-router-dom';
import {QueryFilterFields, QueryFilters} from "../types.ts";

const debounce = (func: (newParams: any) => void, delay: number) => {
    let timerId: ReturnType<typeof setTimeout> | undefined;
    return (...args: any[]) => {
        if (timerId) {
            clearTimeout(timerId);
        }
        // @ts-ignore
        timerId = setTimeout(() => func(...args), delay);
    };
};

export const useFilterQueryParamSync = (): [Partial<QueryFilters>, (updates: Partial<QueryFilters>, replace?: boolean) => void] => {
    const [searchParams, setSearchParams] = useSearchParams();
    const [queryParams, setQueryParams] = useState<Partial<QueryFilters>>({});

    useEffect(() => {
        const parsedParams: Partial<QueryFilters> = {};
        searchParams.forEach((value, key) => {
            if (key.startsWith('filterFields[')) {
                const match = key.match(/^filterFields\[(.+)\]\[(.+)\]$/);
                if (match) {
                    const [_, fieldName, operator] = match;
                    // @ts-ignore
                    parsedParams.filterFields = {
                        ...parsedParams.filterFields,
                        [fieldName]: {operator, value},
                    };
                }
            } else {
                // @ts-ignore
                parsedParams[key as keyof QueryFilters] = value;
            }
        });
        setQueryParams(parsedParams);
    }, [searchParams]);

    const debouncedSetSearchParams = useCallback(
        debounce((newParams: URLSearchParams) => {
            setSearchParams(newParams);
        }, 300),
        [setSearchParams]
    );

    const updateSearchParam = useCallback(
        (updates: Partial<QueryFilters>, replace: boolean = false) => {
            debouncedSetSearchParams((prevParams: URLSearchParams) => {
                const updatedParams = replace ? new URLSearchParams() : new URLSearchParams(prevParams);

                Object.entries(updates).forEach(([key, value]) => {
                    if (key === 'filterFields' && typeof value === 'object') {
                        Object.entries(value as QueryFilterFields).forEach(([field, condition]) => {
                            if (Array.isArray(condition)) {
                                condition.forEach((cond) => {
                                    const paramKey = `filterFields[${field}][${cond.operator}]`;
                                    updatedParams.set(paramKey, String(cond.value));
                                });
                            } else if (condition) {
                                const paramKey = `filterFields[${field}][${condition.operator}]`;
                                updatedParams.set(paramKey, String(condition.value));
                            }
                        });
                    } else {
                        updatedParams.set(key, String(value));
                    }
                });

                return updatedParams;
            });
        },
        [debouncedSetSearchParams]
    );

    return [queryParams, updateSearchParam];
};
