import {useEffect, useState} from 'react';
import dayjs from 'dayjs';
import utc from 'dayjs/plugin/utc';
import {t} from '@lingui/macro';
import classNames from "classnames";

dayjs.extend(utc);

interface CountdownProps {
    targetDate: string;
    onExpiry?: () => void;
    className?: string;
    closeToExpiryClassName?: string;
    displayType?: 'short' | 'long';
}

export const Countdown = ({
                              targetDate,
                              onExpiry,
                              className = '',
                              displayType = 'long',
                              closeToExpiryClassName = ''
                          }: CountdownProps) => {
    const [timeLeft, setTimeLeft] = useState('');
    const [closeToExpiry, setCloseToExpiry] = useState(false);

    useEffect(() => {
        const interval = setInterval(() => {
            const now = dayjs();
            const dateInUTC = dayjs.utc(targetDate);
            const diff = dateInUTC.diff(now);

            if (diff <= 0) {
                setTimeLeft(displayType === 'short' ? '0:00' : t`0 minutes and 0 seconds`);
                clearInterval(interval);
                onExpiry && onExpiry();
                return;
            }

            const days = Math.floor(diff / (1000 * 60 * 60 * 24));
            const hours = Math.floor((diff / (1000 * 60 * 60)) % 24);
            const minutes = Math.floor((diff / 1000 / 60) % 60);
            const seconds = Math.floor((diff / 1000) % 60);

            if (!closeToExpiry) {
                setCloseToExpiry(days === 0 && hours === 0 && minutes < 5)
            }

            if (displayType === 'short') {
                const totalHours = days * 24 + hours;
                const formattedMinutes = String(minutes).padStart(2, '0');

                let display: string;

                if (totalHours > 0) {
                    display = `${totalHours}:${formattedMinutes}:${String(seconds).padStart(2, '0')}`;
                } else if (minutes > 0) {
                    display = seconds > 0
                        ? `${minutes}:${String(seconds).padStart(2, '0')}`
                        : `${minutes}:00`;
                } else {
                    display = String(seconds);
                }

                setTimeLeft(display);
            } else {
                if (days > 0) {
                    setTimeLeft(t`${days} days, ${hours} hours, ${minutes} minutes, and ${seconds} seconds`);
                } else if (hours > 0) {
                    setTimeLeft(t`${hours} hours, ${minutes} minutes, and ${seconds} seconds`);
                } else {
                    setTimeLeft(t`${minutes} minutes and ${seconds} seconds`);
                }
            }
        }, 1000);

        return () => {
            clearInterval(interval);
        };
    }, [targetDate, onExpiry, displayType]);

    return (
        <span className={classNames(className, closeToExpiry ? closeToExpiryClassName : '')}>
             {timeLeft === '' ? '--:--' : timeLeft}
        </span>
    );
};
