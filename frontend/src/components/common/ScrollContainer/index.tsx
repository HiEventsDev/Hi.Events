import classes from './ScrollContainer.module.scss';
import React from "react";

interface ScrollContainerProps {
    children: React.ReactNode
}

export const ScrollContainer = ({children}: ScrollContainerProps) => {
    return (
        <div className={classes.container}>
            {children}
        </div>
    );
}