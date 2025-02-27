import React from "react";
import classes from './PageTitle.module.scss';

interface PageTitleProps extends React.HTMLAttributes<HTMLHeadingElement> {
    children: React.ReactNode,
    subheading?: string
}

export const PageTitle = (props: PageTitleProps) => {
    const { children, subheading, ...rest } = props;

    return (
        <div className={classes.container}>
            <h1 className={classes.title} {...rest}>
                {children}
            </h1>
            {subheading && (
                <div className={classes.subheading}>
                    {subheading}
                </div>
            )}
        </div>
    );
}
