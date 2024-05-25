import classes from './HomepageInfoMessage.module.scss';
import {Button} from "@mantine/core";
import {IconLink} from "@tabler/icons-react";
import React from "react";

interface CheckoutStatusProps {
    message: React.ReactNode
    link?: string;
    linkText?: string;
    iconType?: 'info' | 'processing';
}

export const HomepageInfoMessage = ({message, link, linkText, iconType = 'info'}: CheckoutStatusProps) => {
    const icon = () => {
        if (iconType === 'info') {
            return '/info-icon.svg';
        }
        if (iconType === 'processing') {
            return '/stopwatch-ticket-icon.svg';
        }
    }

    return (
        <div className={classes.checkoutStatus}>
            <div className={classes.iconContainer}>
                <img alt={''} src={icon()}/>
            </div>
            <h3>{message}</h3>
            {(link && linkText) && (
                <Button rightSection={<IconLink size={16}/>} variant={'light'} component={'a'} href={link}>
                    {linkText}
                </Button>
            )}
        </div>
    );
}