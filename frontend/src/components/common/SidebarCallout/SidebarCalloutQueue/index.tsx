import React, {useEffect, useState} from 'react';
import {SidebarCallout} from "../index.tsx";

export interface CalloutConfig {
    icon: React.ReactNode;
    heading: string;
    description: string;
    buttonIcon?: React.ReactNode;
    buttonText?: string;
    onClick?: () => void;
    storageKey: string;
    customButton?: React.ReactNode;
}

interface SidebarCalloutQueueProps {
    callouts: CalloutConfig[];
}

export const SidebarCalloutQueue: React.FC<SidebarCalloutQueueProps> = ({callouts}) => {
    const [activeCalloutIndex, setActiveCalloutIndex] = useState<number | null>(null);

    useEffect(() => {
        // Find the first callout that hasn't been dismissed
        const firstVisibleIndex = callouts.findIndex(callout => {
            if (!callout.storageKey ||
                callout.storageKey.includes('undefined') ||
                callout.storageKey.includes('[object Object]')) {
                return false;
            }

            const isDismissed = localStorage.getItem(callout.storageKey) === 'true';
            return !isDismissed;
        });

        setActiveCalloutIndex(firstVisibleIndex >= 0 ? firstVisibleIndex : null);
    }, [callouts]);

    if (activeCalloutIndex === null || !callouts[activeCalloutIndex]) {
        return null;
    }

    const activeCallout = callouts[activeCalloutIndex];

    return (
        <SidebarCallout
            {...activeCallout}
            onClose={() => {
                // When closed, re-run the effect to find the next callout
                const nextIndex = callouts.findIndex((callout, index) => {
                    if (index <= activeCalloutIndex) return false;

                    if (!callout.storageKey ||
                        callout.storageKey.includes('undefined') ||
                        callout.storageKey.includes('[object Object]')) {
                        return false;
                    }

                    const isDismissed = localStorage.getItem(callout.storageKey) === 'true';
                    return !isDismissed;
                });

                setActiveCalloutIndex(nextIndex >= 0 ? nextIndex : null);
            }}
        />
    );
};
