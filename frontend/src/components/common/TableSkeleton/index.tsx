import {Skeleton} from "@mantine/core";

interface TableSkeletonProps {
    isVisible: boolean,
    numRows?: number,  // numRows is optional
}

export const TableSkeleton = ({isVisible, numRows = 15}: TableSkeletonProps) => {
    if (!isVisible) {
        return null;
    }

    return (
        <>
            <Skeleton height={45} mb="md" radius={'10px'}/>

            {[...Array(numRows)].map((_, index: number) => <Skeleton key={index} mb={20} height={20}/>)}
        </>
    )
}
