import React from "react";
import classes from './PageTitle.module.scss';

interface PageTitleProps extends React.HTMLAttributes<HTMLHeadingElement> {
    children: React.ReactNode,
}

export const PageTitle = (props: PageTitleProps) => {
    return (
        <h1 className={classes.title} {...props}>
            {props.children}
        </h1>
    );
}
