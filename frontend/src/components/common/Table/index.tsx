import classes from './Table.module.scss'
import React from "react";
import {Card} from "../Card";
import {Table as MantineTable} from "@mantine/core";

interface TableHeadProps {
    children: React.ReactNode;
}

export const TableHead = ({children}: TableHeadProps) => {
    return (
        <MantineTable.Thead className={classes.tableHead}>
        {children}
        </MantineTable.Thead>
    )
}

 interface TableProps {
    children: React.ReactNode;
}

export const Table =({children}: TableProps) => {
    return (
        <Card className={classes.card}>
            <MantineTable.ScrollContainer minWidth={200}>
                <MantineTable className={classes.table}>
                    {children}
                </MantineTable>
            </MantineTable.ScrollContainer>
        </Card>
    );
}