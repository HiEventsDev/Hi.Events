import React, {useEffect, useState} from 'react';
import {Button, CloseButton} from '@mantine/core';
import classes from './SidebarCallout.module.scss';

interface SidebarCalloutProps {
    icon: React.ReactNode;
    heading: string;
    description: string;
    buttonIcon?: React.ReactNode;
    buttonText?: string;
    onClick?: () => void;
    onClose?: () => void;
    storageKey?: string;
    customButton?: React.ReactNode;
    isDismissible?: boolean;
}

export const SidebarCallout: React.FC<SidebarCalloutProps> = ({
                                                                  icon,
                                                                  heading,
                                                                  description,
                                                                  buttonIcon,
                                                                  buttonText,
                                                                  onClick,
                                                                  onClose,
                                                                  storageKey = 'sidebar-callout-dismissed',
                                                                  customButton,
                                                                  isDismissible = true,
                                                              }) => {
    const [isVisible, setIsVisible] = useState<boolean | null>(null);

    useEffect(() => {
        if (!storageKey || storageKey.includes('undefined') || storageKey.includes('[object Object]')) {
            return;
        }

        const isDismissed = localStorage.getItem(storageKey) === 'true';
        setIsVisible(!isDismissed);
    }, [storageKey]);

    const handleClose = () => {
        setIsVisible(false);
        localStorage.setItem(storageKey, 'true');
        onClose?.();
    };

    if (isVisible === null || !isVisible) {
        return null;
    }

    return (
        <div className={classes.calloutBox}>
            {isDismissible && (
                <CloseButton
                    className={classes.closeButton}
                    onClick={handleClose}
                    size="sm"
                    iconSize={16}
                />
            )}
            <div className={classes.calloutIcon}>
                {icon}
            </div>
            <h4 className={classes.calloutTitle}>
                {heading}
            </h4>
            <p className={classes.calloutText}>
                {description}
            </p>
            {customButton ? (
                customButton
            ) : (
                <Button
                    fullWidth
                    variant="white"
                    size="sm"
                    leftSection={buttonIcon}
                    onClick={onClick}
                    className={classes.calloutButton}
                >
                    {buttonText}
                </Button>
            )}
        </div>
    );
};
