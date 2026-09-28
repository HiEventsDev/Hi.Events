import {useEffect, useRef, useState} from "react";

export const useUsbBarcodeScanner = (enabled: boolean, onCode: (code: string) => void) => {
    const [hidBuffer, setHidBuffer] = useState("");
    const [pageHasFocus, setPageHasFocus] = useState(true);
    const barcodeTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        const handleFocus = () => setPageHasFocus(true);
        const handleBlur = () => setPageHasFocus(false);

        window.addEventListener("focus", handleFocus);
        window.addEventListener("blur", handleBlur);

        return () => {
            window.removeEventListener("focus", handleFocus);
            window.removeEventListener("blur", handleBlur);
        };
    }, []);

    useEffect(() => {
        if (!enabled) {
            setHidBuffer("");
            return;
        }

        const handleKeyPress = (e: KeyboardEvent) => {
            if (e.target instanceof HTMLInputElement || e.target instanceof HTMLTextAreaElement) {
                return;
            }

            if (e.key === "Enter") {
                if (hidBuffer.length > 0) {
                    onCode(hidBuffer);
                    setHidBuffer("");
                }
            } else if (e.key.length === 1) {
                setHidBuffer(prev => {
                    const newBuffer = prev + e.key;

                    if (barcodeTimeoutRef.current) {
                        clearTimeout(barcodeTimeoutRef.current);
                    }

                    barcodeTimeoutRef.current = setTimeout(() => {
                        if (newBuffer.startsWith("A-") && newBuffer.length > 3) {
                            onCode(newBuffer);
                        }
                        setHidBuffer("");
                    }, 100);

                    return newBuffer;
                });
            }
        };

        window.addEventListener("keypress", handleKeyPress);

        return () => {
            window.removeEventListener("keypress", handleKeyPress);
            if (barcodeTimeoutRef.current) {
                clearTimeout(barcodeTimeoutRef.current);
            }
        };
    }, [enabled, hidBuffer, onCode]);

    return {hidBuffer, pageHasFocus};
};
