import React from 'react';
import {Popover as MantinePopover, PopoverProps as MantinePopoverProps} from "@mantine/core";


interface PopoverProps extends MantinePopoverProps {
    children: React.ReactNode;
    title: React.ReactNode;
}

export const Popover = ({children, title, ...props}: PopoverProps) => {
    return (
        <MantinePopover {...props}>
            <MantinePopover.Target>
                <div style={{cursor: "pointer", display: "inline-flex"}}>
                    {children}
                </div>
            </MantinePopover.Target>
            <MantinePopover.Dropdown>
                {title}
            </MantinePopover.Dropdown>
        </MantinePopover>
    );
}
