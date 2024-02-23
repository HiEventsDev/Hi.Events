import {Box, Pagination as MantinePagination, PaginationProps} from "@mantine/core";

export const Pagination = (props: PaginationProps) => (
    <Box mt={20}>
        <MantinePagination {...props} />
    </Box>
);