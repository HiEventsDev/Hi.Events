import {Drawer, DrawerProps} from "@mantine/core";
import React from "react";
import classes from "./SideDrawer.module.scss";

interface SideDrawerProps {
    heading?: string | React.ReactNode,
}

export const SideDrawer = (props: DrawerProps & SideDrawerProps) => {
    return (
        <Drawer
            {...props}
            overlayProps={{
                opacity: 0.55,
                blur: 3,
            }}
            position="right"
            size={'xl'}
            withCloseButton={true}
            title={props.heading}
            closeOnClickOutside={false}
            classNames={{
                title: classes.sideDrawerTitle,
            }}
        >
            <div style={{padding: '15px', paddingTop: 0}}>
                {props.children}
            </div>
        </Drawer>
    )
}
