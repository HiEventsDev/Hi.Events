import classes from "./CheckoutContent.module.scss";
import React from "react";
import classNames from "classnames";

interface MainContentProps {
    children: React.ReactNode;
    hasFooter?: boolean;
}

export const CheckoutContent = ({children, hasFooter}: MainContentProps) => {
    return (
        <main className={classNames(classes.main, hasFooter && classes.hasFooter)}>
            <div className={classes.innerContainer}>
                {children}
            </div>
        </main>
    )
}