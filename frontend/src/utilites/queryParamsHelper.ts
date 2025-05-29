import {QueryFilterCondition, QueryFilters, QueryFilterOperator} from "../types.ts";

export const queryParamsHelper = {
    PER_PAGE_PARAM: "per_page",
    PAGE_PARAM: "page",
    QUERY_PARAM: "query",
    SORT_BY_PARAM: "sort_by",
    SORT_DIRECTION_PARAM: "sort_direction",
    FILTER_FIELDS: "filter_fields",

    DEFAULT_PER_PAGE: 20,
    DEFAULT_PAGE: 1,

    /**
     * Get a param from the URL
     *
     * @param param {string}
     * @param defaultReturn {*}
     */
    getParam: (param: string, defaultReturn: string | number = ''): string | number => {
        if (typeof window === 'undefined') {
            return defaultReturn;
        }

        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get(param) || defaultReturn;
    },

    /**
     * Build a query string of filter params
     *
     * @example "?per_page=10&page=1"
     */
    buildQueryString: (
        {pageNumber, perPage, query, sortBy, sortDirection, filterFields = {}, additionalParams = {}}: QueryFilters
    ): string => {
        const baseParams: any = {
            [queryParamsHelper.PAGE_PARAM]: pageNumber ||
            queryParamsHelper.getParam(queryParamsHelper.PAGE_PARAM, queryParamsHelper.DEFAULT_PAGE),

            [queryParamsHelper.PER_PAGE_PARAM]: perPage ||
            queryParamsHelper.getParam(queryParamsHelper.PER_PAGE_PARAM, queryParamsHelper.DEFAULT_PER_PAGE),

            [queryParamsHelper.QUERY_PARAM]: query ||
            queryParamsHelper.getParam(queryParamsHelper.QUERY_PARAM, ''),

            [queryParamsHelper.SORT_BY_PARAM]: sortBy ||
            queryParamsHelper.getParam(queryParamsHelper.SORT_BY_PARAM, ''),

            [queryParamsHelper.SORT_DIRECTION_PARAM]: sortDirection ||
            queryParamsHelper.getParam(queryParamsHelper.SORT_DIRECTION_PARAM, ''),
        };

        const filterParams = Object.entries(filterFields).reduce<Record<string, string>>((acc, [key, value]) => {
            if (Array.isArray(value)) {
                value.forEach((condition: QueryFilterCondition) => {
                    const paramKey = `filter_fields[${key}][${condition.operator}]`;
                    acc[paramKey] = String(condition.value);
                });
            } else if (typeof value === 'object' && value !== null) {
                const condition = value as QueryFilterCondition;
                const paramKey = `filter_fields[${key}][${condition.operator}]`;
                acc[paramKey] = String(condition.value);
            }
            return acc;
        }, {});

        const additionalParamsProcessed = Object.entries(additionalParams).reduce<Record<string, string>>((acc, [key, value]) => {
            acc[key] = String(value);
            return acc;
        }, {});

        const combinedParams = {...baseParams, ...filterParams, ...additionalParamsProcessed};

        return '?' + new URLSearchParams(combinedParams).toString();
    },

    /**
     * Validate if a string is a valid QueryFilterOperator
     */
    isValidOperator: (operator: string): operator is QueryFilterOperator => {
        return Object.values(QueryFilterOperator).includes(operator as QueryFilterOperator);
    },

    /**
     * Create query filters from URL search params (useful for SSR)
     */
    createQueryFiltersFromSearchParams: (searchParams: URLSearchParams): QueryFilters => {
        const filterFields: Record<string, QueryFilterCondition | QueryFilterCondition[]> = {};
        const additionalParams: Record<string, any> = {};

        // Parse filter_fields from URL params
        // Format: filter_fields[field_name][operator]=value
        searchParams.forEach((value, key) => {
            const filterMatch = key.match(/^filter_fields\[([^\]]+)\]\[([^\]]+)\]$/);
            if (filterMatch) {
                const [, fieldName, operatorString] = filterMatch;

                // Validate operator
                if (!queryParamsHelper.isValidOperator(operatorString)) {
                    console.warn(`Invalid filter operator: ${operatorString}. Skipping filter for field: ${fieldName}`);
                    return;
                }

                const condition: QueryFilterCondition = {
                    operator: operatorString as QueryFilterOperator,
                    value
                };

                if (filterFields[fieldName]) {
                    // Convert to array if multiple conditions for same field
                    if (Array.isArray(filterFields[fieldName])) {
                        (filterFields[fieldName] as QueryFilterCondition[]).push(condition);
                    } else {
                        filterFields[fieldName] = [filterFields[fieldName] as QueryFilterCondition, condition];
                    }
                } else {
                    filterFields[fieldName] = condition;
                }
            } else if (!key.startsWith('page') && !key.startsWith('per_page') &&
                !key.startsWith('query') && !key.startsWith('sort_by') &&
                !key.startsWith('sort_direction')) {
                // Capture other params as additional params
                additionalParams[key] = value;
            }
        });

        return {
            pageNumber: parseInt(searchParams.get(queryParamsHelper.PAGE_PARAM) || String(queryParamsHelper.DEFAULT_PAGE)),
            perPage: parseInt(searchParams.get(queryParamsHelper.PER_PAGE_PARAM) || String(queryParamsHelper.DEFAULT_PER_PAGE)),
            query: searchParams.get(queryParamsHelper.QUERY_PARAM) || '',
            sortBy: searchParams.get(queryParamsHelper.SORT_BY_PARAM) || '',
            sortDirection: searchParams.get(queryParamsHelper.SORT_DIRECTION_PARAM) || '',
            filterFields,
            additionalParams
        };
    }
};
