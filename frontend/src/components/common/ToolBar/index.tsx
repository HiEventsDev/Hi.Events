import React from "react";
import {Card} from "../Card";
import classes from './ToolBar.module.scss'

interface ToolBarProps {
    children: React.ReactNode[] | React.ReactNode,
    searchComponent?: () => React.ReactNode,
}

export const ToolBar = ({searchComponent, children}: ToolBarProps) => {
    return (
        <Card className={classes.card}>
            <div className={classes.wrapper}>
                <div className={classes.searchBar}>
                    {searchComponent && searchComponent()}
                </div>
                <div className={classes.actions}>
                    {children}
                </div>
            </div>
        </Card>
    )
}