import React from "react";
import classNames from "classnames";
import classes from './ToolBar.module.scss';

interface ToolBarProps {
    children?: React.ReactNode[] | React.ReactNode;
    searchComponent?: () => React.ReactNode;
    filterComponent?: React.ReactNode;
    className?: string;
    embedded?: boolean;
}

export const ToolBarSlot = ({ref}: { ref: React.Ref<HTMLDivElement> }) => <div className={classes.slot} ref={ref}/>;

export const ToolBar: React.FC<ToolBarProps> = ({
    searchComponent,
    filterComponent,
    children,
    className,
    embedded = false,
}) => {
    return (
        <div className={classNames(classes.toolbar, embedded && classes.embedded, className)}>
            <div className={classes.bar}>
                {searchComponent && (
                    <div className={classes.searchSlot}>
                        {searchComponent()}
                    </div>
                )}
                {filterComponent && (
                    <div className={classes.filterSlot}>
                        {filterComponent}
                    </div>
                )}
                {children && (
                    <div className={classes.actions}>
                        {children}
                    </div>
                )}
            </div>
        </div>
    );
};
