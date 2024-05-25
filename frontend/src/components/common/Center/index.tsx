import classes from './Center.module.scss'
import React from "react";

export const Center = ({children}: { children: React.ReactNode }) => {
    return (
        <div className={classes.container}>
            {children}
        </div>
    )
}