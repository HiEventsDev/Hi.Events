import {useEffect, useState} from "react";

export const useWindowWidth = () => {
    const initialWidth = typeof window !== 'undefined' ? window?.innerWidth : 0;
    const [width, setWidth] = useState(initialWidth);

    useEffect(() => {
        const handleResize = () => {
            setWidth(window?.innerWidth);
        };

        const debouncedHandleResize = debounce(handleResize, 100);
        window?.addEventListener('resize', debouncedHandleResize);

        return () => {
            window?.removeEventListener('resize', debouncedHandleResize);
        };
    }, []);

    return width;
}


function debounce(fn: Function, delay: number) {
    let timerId: NodeJS.Timeout;
    return function (...args: any[]) {
        if (timerId) {
            clearTimeout(timerId);
        }
        timerId = setTimeout(() => {
            fn(...args);
        }, delay);
    }
}