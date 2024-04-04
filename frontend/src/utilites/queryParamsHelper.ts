import {QueryFilterFields, QueryFilters} from "../types.ts";

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
        const urlParams = new URLSearchParams(window?.location.search);
        return urlParams.get(param) || defaultReturn;
    },

    /**
     * Build a query string of filter params
     *
     * @example "?per_page=10&page=1"
     */
    buildQueryString: (
        {pageNumber, perPage, query, sortBy, sortDirection, filterFields = {}}: QueryFilters
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

        const filterParams = Object.entries(filterFields).reduce<QueryFilterFields>((acc, [key, value]) => {
            acc[`filter_fields[${key}]`] = value;
            return acc;
        }, {});

        const combinedParams = { ...baseParams, ...filterParams };

        return '?' + new URLSearchParams(combinedParams).toString();    }
};
