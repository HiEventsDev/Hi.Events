import {useEffect, useState} from 'react';
import dayjs from 'dayjs';
import utc from 'dayjs/plugin/utc';
import {t} from '@lingui/macro';

dayjs.extend(utc);

interface CountdownProps {
    targetDate: string;
    onExpiry?: () => void;
    className?: string;
}

export const Countdown = ({targetDate, onExpiry, className = ''}: CountdownProps) => {
    const [timeLeft, setTimeLeft] = useState('');

    useEffect(() => {
        const interval = setInterval(() => {
            const now = dayjs();

            const dateInUTC = dayjs.utc(targetDate);

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
    }, [targetDate, onExpiry]);

    return <span className={className}>{timeLeft === '' ? '...' : timeLeft}</span>;
};
