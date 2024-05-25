export const getSessionIdentifier = (): string => {
    const hashString = (str: string): string => {
        let hash = 2166136261;
        for (let i = 0; i < str.length; i++) {
            hash ^= str.charCodeAt(i);
            hash += (hash << 1) + (hash << 4) + (hash << 7) + (hash << 8) + (hash << 24);
        }
        let hexHash = (hash >>> 0).toString(16);
        while (hexHash.length < 8) {
            hexHash = '0' + hexHash;
        }
        return hexHash.slice(0, 12);
    };
    const getFingerprint = (): string => {
        const fingerprint = {
            userAgent: navigator.userAgent,
            screenResolution: `${screen.width}x${screen.height}`,
            colorDepth: screen.colorDepth,
            timezoneOffset: new Date().getTimezoneOffset(),
            language: navigator.language || (navigator as any).userLanguage || 'unknown',
            platform: navigator.platform || 'unknown',
            canvas: getCanvasFingerprint(),
            hardwareConcurrency: navigator.hardwareConcurrency || 'unknown',
            deviceMemory: (navigator as any).deviceMemory || 'unknown',
            maxTouchPoints: navigator.maxTouchPoints || 'unknown',
            vendor: navigator.vendor || 'unknown'
        };

        const fingerprintString = JSON.stringify(fingerprint);
        return hashString(fingerprintString);
    };

    const getCanvasFingerprint = (): string => {
        try {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            const text = 'Session ID';

            if (!ctx) {
                return 'unsupported';
            }

            ctx.textBaseline = 'top';
            ctx.font = '16px Arial';
            ctx.textBaseline = 'alphabetic';
            ctx.fillStyle = '#f60';
            ctx.fillRect(125, 1, 62, 20);
            ctx.fillStyle = '#069';
            ctx.fillText(text, 2, 15);
            ctx.fillStyle = 'rgba(102, 204, 0, 0.7)';
            ctx.fillText(text, 4, 17);
            return canvas.toDataURL();
        } catch (e) {
            return 'unsupported';
        }
    };

    try {
        return getFingerprint();
    } catch (e) {
        const fallback = {
            userAgent: navigator.userAgent,
            screenResolution: `${screen.width}x${screen.height}`,
            colorDepth: screen.colorDepth,
            timezoneOffset: new Date().getTimezoneOffset(),
        };
        const fallbackString = JSON.stringify(fallback);
        return hashString(fallbackString);
    }
};