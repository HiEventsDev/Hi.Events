import {useCallback, useEffect, useMemo, useRef} from 'react';
import {useSearchParams} from 'react-router';
import {QueryFilters} from "../types";

const TYPING_DELAY_MS = 300;

type FilterParamUpdates<T> = { [K in keyof T]?: T[K] | null };

const parseSearchParams = <T extends Partial<QueryFilters>>(searchParams: URLSearchParams): T => {
    const parsedParams: Record<string, unknown> = {};
    const filterFields: Record<string, { operator: string, value: string | string[] }> = {};

    searchParams.forEach((value, key) => {
        if (key.startsWith('filterFields[')) {
            const match = key.match(/^filterFields\[(.+)\]\[(.+)\]$/);
            if (match) {
                const [, fieldName, operator] = match;
                filterFields[fieldName] = {
                    operator,
                    value: value.includes(',') ? value.split(',') : value
                };
            }
        } else {
            parsedParams[key] = value;
        }
    });

    if (Object.keys(filterFields).length > 0) {
        parsedParams.filterFields = filterFields;
    }

    return parsedParams as T;
};

export const useFilterQueryParamSync = <T extends Partial<QueryFilters> = Partial<QueryFilters>>(): [
    T,
    (updates: FilterParamUpdates<T>, replace?: boolean) => void
] => {
    const [searchParams, setSearchParams] = useSearchParams();
    const queryParams = useMemo(() => parseSearchParams<T>(searchParams), [searchParams]);
    const pendingUpdates = useRef<{ updates: FilterParamUpdates<T>, replace: boolean }[]>([]);
    const latestSetSearchParams = useRef(setSearchParams);
    latestSetSearchParams.current = setSearchParams;

    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

    const flush = useCallback(() => {
            if (timer.current) {
                clearTimeout(timer.current);
                timer.current = null;
            }

            const queued = pendingUpdates.current;
            pendingUpdates.current = [];

            if (queued.length === 0) {
                return;
            }

            latestSetSearchParams.current(previous => {
                let newParams = new URLSearchParams(previous);

                queued.forEach(({updates, replace}) => {
                    if (replace) {
                        newParams = new URLSearchParams();
                    }

                    Object.entries(updates).forEach(([key, value]) => {
                        if (key === 'filterFields' && value) {
                            Object.entries(value as Record<string, { operator: string, value: unknown } | undefined>).forEach(([field, condition]) => {
                                if (condition) {
                                    const paramKey = `filterFields[${field}][${condition.operator}]`;
                                    if (Array.isArray(condition.value)) {
                                        newParams.set(paramKey, condition.value.join(','));
                                    } else {
                                        newParams.set(paramKey, String(condition.value));
                                    }
                                }
                            });
                        } else if (value === null) {
                            newParams.delete(key);
                        } else if (value !== undefined) {
                            newParams.set(key, String(value));
                        }
                    });
                });

                return newParams;
            });
        }, []);

    useEffect(() => () => {
        if (timer.current) {
            clearTimeout(timer.current);
        }
        pendingUpdates.current = [];
    }, []);

    const updateSearchParams = useCallback(
        (updates: FilterParamUpdates<T>, replace = false) => {
            pendingUpdates.current.push({updates, replace});

            if (!('query' in updates)) {
                flush();
                return;
            }

            if (timer.current) {
                clearTimeout(timer.current);
            }
            timer.current = setTimeout(flush, TYPING_DELAY_MS);
        },
        [flush]
    );

    return [queryParams, updateSearchParams];
};
