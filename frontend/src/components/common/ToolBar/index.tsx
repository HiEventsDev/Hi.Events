import React from "react";
import {Card} from "../Card";
import classes from './ToolBar.module.scss';
import {Group} from '@mantine/core';

interface ToolBarProps {
    children?: React.ReactNode[] | React.ReactNode;
    searchComponent?: () => React.ReactNode;
    filterComponent?: React.ReactNode;
    className?: string;
}

export const ToolBar: React.FC<ToolBarProps> = ({
                                                    searchComponent,
                                                    filterComponent,
                                                    children,
                                                    className,
                                                }) => {
    return (
        <Card className={`${classes.card} ${className || ''}`}>
            <div className={classes.wrapper}>
                {searchComponent && (
                    <div className={classes.searchBar}>
                        {searchComponent()}
                    </div>
                )}

                <Group className={classes.filterAndActions} gap="sm">
                    {filterComponent && (
                        <div className={classes.filter}>
                            {filterComponent}
                        </div>
                    )}
                    {children && (
                        <div className={classes.actions}>
                            {children}
                        </div>
                    )}
                </Group>
            </div>
        </Card>
    );
};
