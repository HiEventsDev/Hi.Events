import { useEffect } from 'react';

type CallbackFunction = (matches?: RegExpMatchArray) => void;

export const useUrlHash = (patternOrString: RegExp | string, callback: CallbackFunction): void => {
    useEffect(() => {
        const checkHash = (): void => {
            const { hash, pathname, search } = window.location;

            if (hash) {
                if (typeof patternOrString === 'string' && hash === `#${patternOrString}`) {
                    callback();
                    window.history.replaceState(null, '', pathname + search);
                } else if (patternOrString instanceof RegExp) {
                    const match = hash.match(patternOrString);
                    if (match) {
                        callback(match);
                        window.history.replaceState(null, '', pathname + search);
                    }
                }
            }
        };

        checkHash();
        window.addEventListener('hashchange', checkHash);

        return () => {
            window.removeEventListener('hashchange', checkHash);
        };
    }, [patternOrString, callback]);
};
