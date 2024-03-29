import classes from "./CheckoutContent.module.scss";
import React from "react";

interface MainContentProps {
    children: React.ReactNode;
}

export const CheckoutContent = ({children}: MainContentProps) => {
    return (
        <main className={classes.main}>
            <div className={classes.innerContainer}>
                {children}
            </div>
        </main>
    )
}