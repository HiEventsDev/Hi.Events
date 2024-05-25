import {useCallback, useEffect, useState} from 'react';
import {useSearchParams} from 'react-router-dom';
import {QueryFilters} from "../types.ts";

const debounce = (func: (newParams: any) => void, delay: number) => {
    let timerId: ReturnType<typeof setTimeout> | undefined;
    return (...args: any[]) => {
        if (timerId) {
            clearTimeout(timerId);
        }
        // eslint-disable-next-line @typescript-eslint/ban-ts-comment
        // @ts-ignore
        timerId = setTimeout(() => func(...args), delay);
    };
};

export const useFilterQueryParamSync = (): [Partial<QueryFilters>, (updates: Partial<QueryFilters>) => void] => {
    const [searchParams, setSearchParams] = useSearchParams();
    const [queryParams, setQueryParams] = useState<Partial<QueryFilters>>({});

    useEffect(() => {
        const parsedParams: Partial<QueryFilters> = {};
        searchParams.forEach((value, key) => {
            // eslint-disable-next-line @typescript-eslint/ban-ts-comment
            // @ts-ignore
            parsedParams[key as keyof QueryFilters] = value;
        });
        setQueryParams(parsedParams);
    }, [searchParams]);

    // eslint-disable-next-line react-hooks/exhaustive-deps
    const debouncedSetSearchParams = useCallback(debounce((newParams: URLSearchParams) => {
        setSearchParams(newParams);
    }, 300), [setSearchParams]);

    const updateSearchParam = useCallback((updates: Partial<QueryFilters>) => {
        debouncedSetSearchParams((prevParams: URLSearchParams) => {
            const updatedParams = new URLSearchParams(prevParams);
            Object.entries(updates).forEach(([key, value]) => {
                updatedParams.set(key, String(value));
            });
            return updatedParams;
        });
    }, [debouncedSetSearchParams]);

    return [queryParams, updateSearchParam];
};
