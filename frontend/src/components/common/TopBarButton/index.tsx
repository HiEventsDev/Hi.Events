import { Button, ButtonProps } from '@mantine/core';
import React, { forwardRef } from 'react';
import classes from './TopBarButton.module.scss';

interface TopBarButtonProps extends ButtonProps {
    children: React.ReactNode;
}

export const TopBarButton = forwardRef<HTMLButtonElement, TopBarButtonProps>(
    ({ children, className, ...props }, ref) => {
        return (
            <Button
                ref={ref}
                className={`${classes.topBarButton} ${className || ''}`}
                {...props}
            >
                {children}
            </Button>
        );
    }
);

TopBarButton.displayName = 'TopBarButton';
