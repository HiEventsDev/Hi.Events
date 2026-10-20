import React from "react";
import {Card} from "../Card";
import classes from './ToolBar.module.scss';
import {t} from "@lingui/macro";

interface ToolBarProps {
    children?: React.ReactNode[] | React.ReactNode;
    searchComponent?: () => React.ReactNode;
    filterComponent?: React.ReactNode;
    resultCount?: number;
    resultLabel?: string;
    className?: string;
}

export const ToolBar: React.FC<ToolBarProps> = ({
    searchComponent,
    filterComponent,
    children,
    resultCount,
    resultLabel,
    className,
}) => {
    return (
        <Card className={`${classes.toolbar} ${className || ''}`}>
            <div className={classes.rowPrimary}>
                {searchComponent && (
                    <div className={classes.searchSlot}>
                        {searchComponent()}
                    </div>
                )}
                {children && (
                    <div className={classes.actions}>
                        {children}
                    </div>
                )}
            </div>

            {(filterComponent || resultCount !== undefined) && (
                <div className={classes.rowFilters}>
                    {filterComponent && (
                        <div className={classes.filterSlot}>
                            {filterComponent}
                        </div>
                    )}
                    {resultCount !== undefined && (
                        <span className={classes.resultCount}>
                            {resultCount.toLocaleString()} {resultLabel || t`results`}
                        </span>
                    )}
                </div>
            )}
        </Card>
    );
};
