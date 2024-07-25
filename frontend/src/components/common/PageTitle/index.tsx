import React from "react";
import classes from './PageTitle.module.scss';

interface PageTitleProps {
    children: React.ReactNode,
}

export const PageTitle = ({children}: PageTitleProps) => {
    return (
        <h1 className={classes.title}>{children}</h1>
    );
}
