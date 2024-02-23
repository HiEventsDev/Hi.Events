import { useEffect, useState } from 'react';
import dayjs from 'dayjs';
import utc from 'dayjs/plugin/utc';
import { t } from '@lingui/macro';

dayjs.extend(utc);

interface CountdownProps {
    targetDate: string;
    onExpiry?: () => void;
}

export const Countdown = ({ targetDate, onExpiry }: CountdownProps) => {
    const [timeLeft, setTimeLeft] = useState('');

    useEffect(() => {
        const interval = setInterval(() => {
            // Current time in the client's local timezone
            const now = dayjs();

            // Target time assumed to be in UTC
            const dateInUTC = dayjs.utc(targetDate);

            // Calculate the difference in milliseconds
            const diff = dateInUTC.diff(now);

            if (diff <= 0) {
                setTimeLeft(t`0 minutes and 0 seconds`);
                clearInterval(interval);
                onExpiry && onExpiry();
                return;
            }

            const minutes = Math.floor(diff / 1000 / 60);
            const seconds = Math.floor((diff / 1000) % 60);
            setTimeLeft(t`${minutes} minutes and ${seconds} seconds`);
        }, 1000);

        return () => {
            clearInterval(interval);
        };
    }, [targetDate, onExpiry]); // Include onExpiry in the dependency array

    return <span>{timeLeft}</span>;
};
