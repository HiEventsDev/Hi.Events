import { useCallback, useMemo } from 'react';
import { useSearchParams } from 'react-router';
import { QueryFilters } from "../types";

type FilterCondition = {
    operator: string;
    value: string | string[];
};

const debounce = <T extends (...args: any[]) => void>(func: T, delay: number) => {
    let timerId: ReturnType<typeof setTimeout>;
    return (...args: Parameters<T>) => {
        if (timerId) clearTimeout(timerId);
        timerId = setTimeout(() => func(...args), delay);
    };
};

export const useFilterQueryParamSync = (): [
    Partial<QueryFilters>,
    (updates: Partial<QueryFilters>, replace?: boolean) => void
] => {
    const [searchParams, setSearchParams] = useSearchParams();

    const queryParams = useMemo(() => {
        const parsedParams: Partial<QueryFilters> & Record<string, any> = {};

        searchParams.forEach((value, key) => {
            if (key.startsWith('filterFields[')) {
                const match = key.match(/^filterFields\[(.+)\]\[(.+)\]$/);
                if (match) {
                    const [, fieldName, operator] = match;

                    if (!parsedParams.filterFields) {
                        parsedParams.filterFields = {};
                    }

                    (parsedParams.filterFields as Record<string, FilterCondition>)[fieldName] = {
                        operator,
                        value: value.includes(',') ? value.split(',') : value
                    };
                }
            } else {
                parsedParams[key] = value;
            }
        });

        return parsedParams;
    }, [searchParams]);

    const debouncedSetSearchParams = useCallback(
        debounce((params: URLSearchParams) => {
            setSearchParams(params);
        }, 300),
        [setSearchParams]
    );

    const updateSearchParams = useCallback(
        (updates: Partial<QueryFilters>, replace: boolean = false) => {
            // Create new params based on current URL state
            const newParams = replace ? new URLSearchParams() : new URLSearchParams(searchParams);

            if (replace) {
                // Clean up existing filter fields
                Array.from(newParams.keys()).forEach((key) => {
                    if (key.startsWith('filterFields[')) {
                        newParams.delete(key);
                    }
                });
            }

            Object.entries(updates).forEach(([key, value]) => {
                if (key === 'filterFields' && value) {
                    Object.entries(value).forEach(([field, condition]) => {
                        if (condition && typeof condition === 'object' && 'operator' in condition) {
                            const paramKey = `filterFields[${field}][${condition.operator}]`;
                            if (Array.isArray(condition.value)) {
                                newParams.set(paramKey, condition.value.join(','));
                            } else {
                                newParams.set(paramKey, String(condition.value));
                            }
                        }
                    });
                } else if (value !== undefined && value !== null) {
                    newParams.set(key, String(value));
                }
            });

            debouncedSetSearchParams(newParams);
        },
        [searchParams, debouncedSetSearchParams]
    );

    return [queryParams, updateSearchParams];
};
